<?php

/*
 * This file is part of Piplin.
 *
 * Copyright (C) 2016-2017 piplin.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Piplin\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use McCool\LaravelAutoPresenter\Facades\AutoPresenter;
use Piplin\Bus\Jobs\AbortTaskJob;
use Piplin\Bus\Jobs\CreateTaskJob;
use Piplin\Bus\Jobs\DeployDraftJob;
use Piplin\Http\Controllers\Controller;
use Piplin\Http\Requests\StoreTaskRequest;
use Piplin\Models\BuildPlan;
use Piplin\Models\Command;
use Piplin\Models\Task;
use Piplin\Models\Environment;
use Piplin\Models\Project;
use Piplin\Models\Release;
use Symfony\Component\HttpFoundation\Response;

/**
 * The controller for showing the status of tasks.
 */
class TaskController extends Controller
{
    /**
     * Show the task details.
     *
     * @param Task $task
     *
     * @return Response|View
     */
    public function show(Task $task)
    {
        $this->authorize('view', $task->project);

        $output   = [];
        $envLocks = [];
        foreach ($task->steps as $step) {
            foreach ($step->logs as $log) {
                if (!$log->server) {
                    continue;
                }
                $log->cabinet          = false;
                $log->environment_name = null;
                if ($log->environment) {
                    if (!$log->server->targetable instanceof Environment) {
                        $log->cabinet = true;
                    }

                    if (!isset($envLocks[$step->id . '_' . $log->environment_id])) {
                        $log->environment_name = $log->environment->name;
                        $envLocks[$step->id . '_' . $log->environment_id] = true;
                    }
                } elseif ($log->server->targetable instanceof BuildPlan) {
                    $log->environment_name = trans('plans.agent');
                }

                $log->runtime = ($log->runtime() === false ?
                        null : AutoPresenter::decorate($log)->readable_runtime);
                $log->output  = ((is_null($log->output) || !strlen($log->output)) ? null : '');

                $output[] = $log;
            }
        }

        $data = [
            'title'  => $task->title,
            'task'   => $task,
            'output' => json_encode($output), // PresentableInterface does not correctly json encode the models
        ];

        $project = $task->project ?: null;
        if ($project) {
            $data['breadcrumb'] = [
                ['url' => route('projects', ['task' => $project->id]), 'label' => $project->name],
            ];
            $data['project']  = $project;
            $data['subtitle'] = '(' . $task->short_commit . ' - ' . $task->branch . ')';
        }

        if ($task->is_build) {
            $max = Release::getMaxInternalId($project->id);
            $data['release_name'] = 'Release-' . ($max + 1);
            $data['releases'] = $task->releases;
        }

        return view('dashboard.tasks.show', $data);
    }

    /**
     * Adds a task for the specified project to the queue.
     *
     * @param StoreTaskRequest $request
     *
     * @return Response|array
     */
    public function store(StoreTaskRequest $request)
    {
        $project = Project::findOrFail($request->get('project_id'));

        $this->authorize('deploy', $project);

        $fields = [
            'reason'          => $request->get('reason'),
            'project_id'      => $project->id,
            'targetable_type' => $request->get('targetable_type'),
            'targetable_id'   => $request->get('targetable_id'),
            'environments'    => $request->get('environments'),
            'branch'          => $project->branch,
            'optional'        => [],
        ];

        /*
        if ($project->deployPlan->environments->count() === 0) {
            return [
                'success' => false,
            ];
        }
        */

        // If allow other branches is set, check for post data
        if ($project->allow_other_branch) {
            if ($request->has('source') && $request->has('source_' . $request->get('source'))) {
                $fields['branch'] = $request->get('source_' . $request->get('source'));

                if ($request->get('source') === 'commit') {
                    $fields['commit'] = $fields['branch'];
                    $fields['branch'] = $project->branch;
                }
            }
        }

        // Get the optional commands and typecast to integers
        if ($request->has('optional') && is_array($request->get('optional'))) {
            $fields['optional'] = array_filter(array_map(function ($value) {
                return filter_var($value, FILTER_VALIDATE_INT);
            }, $request->get('optional')));
        }
        $fields['user_id'] = Auth::user()->id;

        //$fields['payload'] = $request->only('branch');
        $fields['payload'] = $request->payloadOnly();

        dispatch(new CreateTaskJob($project, $fields));

        return [
            'success' => true,
        ];
    }

    /**
     * Loads a previous task and then creates a new task based on it.
     *
     * @param Request    $request
     * @param Task $previous
     *
     * @return Response|array
     */
    public function rollback(Request $request, Task $previous)
    {
        $this->authorize('deploy', $previous->project);

        $optional = [];
        // Get the optional commands and typecast to integers
        if ($request->has('optional') && is_array($request->get('optional'))) {
            $optional = array_filter(array_map(function ($value) {
                return filter_var($value, FILTER_VALIDATE_INT);
            }, $request->get('optional')));
        }

        $fields = [
            'committer'       => $previous->committer,
            'committer_email' => $previous->committer_email,
            'commit'          => $previous->commit,
            'project_id'      => $previous->project_id,
            'targetable_type' => $previous->targetable_type,
            'targetable_id'   => $previous->targetable_id,
            'payload'         => $previous->payload,
            'branch'          => $previous->branch,
            'reason'          => trans('tasks.rollback_reason', [
                                    'reason'          => $request->get('reason'),
                                    'id'              => $previous->id,
                                    'commit'          => $previous->short_commit, ]),
            'optional'        => $optional,
            'environments'    => $previous->environments->pluck('id')->toArray(),
        ];

        $fields['user_id'] = Auth::user()->id;

        dispatch(new CreateTaskJob($previous->project, $fields));

        return [
            'success' => true,
        ];
    }

    /**
     * Execute the task from draft.
     *
     * @param Task $task
     *
     * @return Response|array
     */
    public function deployDraft(Task $task)
    {
        $this->authorize('deploy', $task->project);

        if ($task->isDraft()) {
            dispatch(new DeployDraftJob($task));
        }

        return [
            'success' => true,
        ];
    }

    /**
     * Abort a task.
     *
     * @param Task $task
     *
     * @return Response
     */
    public function abort(Task $task)
    {
        $this->authorize('deploy', $task->project);

        if (!$task->isAborting()) {
            $task->status = Task::ABORTING;
            $task->save();

            dispatch(new AbortTaskJob($task));
        }

        return redirect()->route('tasks.show', [
            'task' => $task->id,
        ]);
    }
}
