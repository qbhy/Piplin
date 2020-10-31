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
use Illuminate\View\View;
use Piplin\Http\Controllers\Controller;
use Piplin\Http\Requests\StoreProjectGroupRequest;
use Piplin\Models\ProjectGroup;
use Symfony\Component\HttpFoundation\Response;

/**
 * Project group management controller.
 */
class ProjectGroupController extends Controller
{
    /**
     * Display a listing of the groups.
     *
     * @return Response|View
     */
    public function index(Request $request)
    {
        $groups = ProjectGroup::orderBy('order')
            ->paginate(config('piplin.items_per_page', 10));

        return view('admin.groups.index', [
            'title' => trans('groups.manage'),
            'groups' => $groups,
            'current_child' => 'groups'
        ]);
    }

    /**
     * Shows the create project group view.
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
     * Store a newly created group in storage.
     *
     * @param  StoreProjectGroupRequest  $request
     *
     * @return Response|ProjectGroup
     */
    public function store(StoreProjectGroupRequest $request)
    {
        return ProjectGroup::create($request->only(
            'name'
        ));
    }

    /**
     * Update the specified group in storage.
     *
     * @param  ProjectGroup              $group
     * @param  StoreProjectGroupRequest  $request
     *
     * @return Response|ProjectGroup
     */
    public function update(ProjectGroup $group, StoreProjectGroupRequest $request)
    {
        $group->update($request->only(
            'name'
        ));

        return $group;
    }

    /**
     * Re-generates the order for the supplied groups.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return Response|array
     */
    public function reorder(Request $request)
    {
        $order = 0;

        foreach ($request->get('groups') as $group_id) {
            $group = ProjectGroup::findOrFail($group_id);
            $group->update([
                'order' => $order,
            ]);

            $order++;
        }

        return [
            'success' => true,
        ];
    }

    /**
     * Remove the specified group from storage.
     *
     * @param  ProjectGroup  $group
     *
     * @return Response|array
     */
    public function destroy(ProjectGroup $group)
    {
        $group->forceDelete();

        return [
            'success' => true,
        ];
    }
}
