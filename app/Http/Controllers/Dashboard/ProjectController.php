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

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Piplin\Bus\Jobs\SetupSkeletonJob;
use Piplin\Http\Controllers\Controller;
use Piplin\Http\Requests\StoreProjectRequest;
use Piplin\Models\Command;
use Piplin\Models\Task;
use Piplin\Models\Project;
use Symfony\Component\HttpFoundation\Response;

/**
 * The controller of projects.
 */
class ProjectController extends Controller
{
    /**
     * The details of an individual project.
     *
     * @param Project $project
     * @param string  $tab
     *
     * @return View
     */
    public function show(Project $project, $tab = '')
    {
        $optional = $project->deployPlan->commands->filter(function (Command $command) {
            return $command->optional;
        });

        $data = [
            'project'         => $project,
            'targetable_type' => get_class($project),
            'targetable_id'   => $project->id,
            'optional'        => $optional,
            'tasks'           => $this->getLatest($project),
            'tab'             => $tab,
            'breadcrumb'      => [
                ['url' => route('projects', ['project' => $project->id]), 'label' => $project->name],
            ],
        ];

        $data['environments'] = $project->deployPlan->environments;
        if ($tab === 'hooks') {
            $data['hooks'] = $project->hooks;
            $data['title'] = trans('hooks.label');
        } elseif ($tab === 'members') {
            $data['members'] = $project->members->toJson();
            $data['title']   = trans('members.label');
        } elseif ($tab === 'environments') {
            $data['title'] = trans('environments.label');
        }

        return view('dashboard.projects.show', $data);
    }

    /**
     * Store a newly created project in storage.
     *
     * @param StoreProjectRequest $request
     *
     * @return Response|Project
     */
    public function store(StoreProjectRequest $request)
    {
        $fields = $request->only(
            'name',
            'description',
            'repository',
            'branch',
            'deploy_path',
            'allow_other_branch'
        );

        $skeleton = null;

        $project = Auth::user()->personalProjects()->create($fields);

        $project->members()->attach([Auth::user()->id]);

        dispatch(new SetupSkeletonJob($project, $skeleton));

        return $project;
    }

    /**
     * Update the specified project in storage.
     *
     * @param Project             $project
     * @param StoreProjectRequest $request
     *
     * @return Response|Project
     */
    public function update(Project $project, StoreProjectRequest $request)
    {
        $project->update($request->only(
            'name',
            'description',
            'repository',
            'branch',
            'deploy_path',
            'allow_other_branch'
        ));

        return $project;
    }

    /**
     * Remove the specified project from storage.
     *
     * @param Project $project
     *
     * @return Response|array
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return [
            'success' => true,
        ];
    }


    /**
     * Recover the status of specified project.
     *
     * @param Project $project
     *
     * @return Response|Project
     */
    public function recover(Project $project)
    {
        $project->status = Project::INITIAL;
        $project->save();

        return $project;
    }

    /**
     * Gets the latest deployments for a project.
     *
     * @param  Project $project
     * @param  int     $paginate
     * @return Paginator
     */
    private function getLatest(Project $project, $paginate = 15)
    {
        return Task::where('project_id', $project->id)
                           ->with('user')
                           ->whereNotNull('started_at')
                           ->orderBy('started_at', 'DESC')
                           ->paginate($paginate);
    }
}
