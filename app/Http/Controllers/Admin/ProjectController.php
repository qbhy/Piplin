<?php

/*
 * This file is part of Piplin.
 *
 * Copyright (C) 2016-2017 piplin.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Piplin\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Piplin\Bus\Jobs\SetupSkeletonJob;
use Piplin\Http\Controllers\Controller;
use Piplin\Http\Requests\StoreProjectRequest;
use Piplin\Models\Key;
use Piplin\Models\Project;
use Piplin\Models\ProjectGroup;
use Symfony\Component\HttpFoundation\Response;

/**
 * The controller for managging projects.
 */
class ProjectController extends Controller
{
    /**
     * Shows all projects.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return Response|View
     */
    public function index(Request $request)
    {
        $projects = Project::orderBy('id', 'desc')
            ->paginate(config('piplin.items_per_page', 10));

        $keys = Key::orderBy('name')
            ->get();

        $groups = ProjectGroup::orderBy('order')
            ->get();

        return view('admin.projects.index', [
            'is_secure' => $request->secure(),
            'title' => trans('projects.manage'),
            'keys' => $keys,
            'groups' => $groups,
            'projects_raw' => $projects,
            'projects' => $projects->toJson(), // Because ProjectPresenter toJson() is not working in the view
            'current_child' => 'projects',
        ]);
    }

    /**
     * Shows the create project view.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\View\View
     */
    public function create(Request $request)
    {
        return $this->index($request)->with('action', 'create');
    }

    /**
     * Store a newly created project in storage.
     *
     * @param  StoreProjectRequest  $request
     *
     * @return Response|Project
     */
    public function store(StoreProjectRequest $request)
    {
        $fields = $request->only(
            'name',
            'repository',
            'branch',
            'targetable_id',
            'key_id',
            'deploy_path',
            'builds_to_keep',
            'url',
            'build_url',
            'allow_other_branch'
        );

        $group_id = array_pull($fields, 'targetable_id');

        if ($group_id && $group = ProjectGroup::find($group_id)) {
            $project = $group->projects()->create($fields);
        } else {
            $project = Auth::user()->personalProjects()->create($fields);
        }

        $project->members()->attach([Auth::user()->id]);

        dispatch(new SetupSkeletonJob($project));

        return $project;
    }

    /**
     * Clone a new project based on skeleton.
     *
     * @param  Project  $skeleton
     * @param  Request  $request
     *
     * @return Response
     */
    public function clone(Project $skeleton, Request $request)
    {
        $fields = $request->only('name');

        if (empty($fields['name'])) {
            $fields['name'] = $skeleton->name.'_Clone';
        }

        $fields['targetable_type'] = $skeleton->targetable_type;
        $fields['targetable_id'] = $skeleton->targetable_id;
        $fields['key_id'] = $skeleton->key_id;
        $fields['deploy_path'] = $skeleton->deploy_path;
        $fields['repository'] = $skeleton->repository;
        $target = Project::create($fields);
        $target->members()->attach([Auth::user()->id]);

        dispatch(new SetupSkeletonJob($target, $skeleton));

        return redirect()->route('projects', [
            'id' => $target->id,
        ]);
    }

    /**
     * Update the specified project in storage.
     *
     * @param  Project              $project
     * @param  StoreProjectRequest  $request
     *
     * @return Response|Project
     */
    public function update(Project $project, StoreProjectRequest $request)
    {
        $fields = $request->only(
            'name',
            'repository',
            'branch',
            'targetable_id',
            'key_id',
            'deploy_path',
            'builds_to_keep',
            'url',
            'build_url',
            'allow_other_branch'
        );

        if ($fields['targetable_id']) {
            $fields['targetable_type'] = ProjectGroup::class;
        }

        $project->update($fields);

        return $project;
    }

    /**
     * Remove the specified model from storage.
     *
     * @param  Project  $project
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
}
