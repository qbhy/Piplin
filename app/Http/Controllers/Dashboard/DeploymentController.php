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
use Illuminate\View\View;
use Piplin\Http\Controllers\Controller;
use Piplin\Models\Command;
use Piplin\Models\DeployPlan;
use Piplin\Models\Task;

/**
 * The controller of deploy plans.
 */
class DeploymentController extends Controller
{
    /**
     * The details of an individual project.
     *
     * @param Request    $request
     * @param DeployPlan $deployPlan
     * @param string     $tab
     *
     * @return View
     */
    public function show(Request $request, DeployPlan $deployPlan, $tab = '')
    {
        $project = $deployPlan->project;

        $optional = $deployPlan->commands->filter(function (Command $command) {
            return $command->optional;
        });

        $data = [
            'project'         => $project,
            'deployPlan'      => $deployPlan,
            'targetable_type' => get_class($deployPlan),
            'targetable_id'   => $deployPlan->id,
            'optional'        => $optional,
            'tasks'           => $this->getLatest($deployPlan),
            'releases'        => $project->releases,
            'tags'            => $project->tags()->reverse(),
            'branches'        => $project->branches(),
            'tab'             => $tab,
            'title'           => trans('tasks.label'),
            'breadcrumb'      => [
                ['url' => route('projects', ['project' => $project->id]), 'label' => $project->name],
                ['url' => route('deployments', ['deployment' => $deployPlan->id]), 'label' => trans('projects.deploy_plan')],
            ],
        ];

        $data['environments'] = $deployPlan->environments;
        if ($tab === 'commands') {
            $data['route']     = 'commands.step';
            $data['variables'] = $deployPlan->variables;
            $data['title']     = trans('commands.label');
        } elseif ($tab === 'config-files') {
            $data['configFiles'] = $deployPlan->configFiles;
            $data['title']       = trans('configFiles.label');
        } elseif ($tab === 'shared-files') {
            $data['sharedFiles'] = $deployPlan->sharedFiles;
            $data['title']       = trans('sharedFiles.tab_label');
        } elseif ($tab === 'environments') {
            $data['title'] = trans('environments.label');
        } elseif ($tab === 'deploy') {
            $data['release_id'] = $request->get('release_id');
            // Don't trigger dialog if page changed
            if ($request->get('page')) {
                $data['tab'] = null;
            }
        }

        return view('dashboard.deployments.show', $data);
    }

    /**
     * Gets the latest deployments for a project.
     *
     * @param  DeployPlan $deployPlan
     * @param  int        $paginate
     * @return array|\Illuminate\Contracts\Pagination\Paginator
     */
    private function getLatest(DeployPlan $deployPlan, $paginate = 15)
    {
        return Task::where('targetable_type', get_class($deployPlan))
                           ->where('targetable_id', $deployPlan->id)
                           ->with('user')
                           ->whereNotNull('started_at')
                           ->orderBy('started_at', 'DESC')
                           ->paginate($paginate);
    }
}
