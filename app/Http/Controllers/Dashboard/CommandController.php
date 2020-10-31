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

use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Piplin\Http\Controllers\Controller;
use Piplin\Http\Requests\StoreCommandRequest;
use Piplin\Models\Command;
use Piplin\Models\BuildPlan;
use Piplin\Models\Project;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for managing commands.
 */
class CommandController extends Controller
{
    /**
     * Display a listing of before/after commands for the supplied stage.
     *
     * @param  mixed   $target
     * @param  string  $action  Either clone, install, activate or purge
     * @return Response|View
     */
    public function index($target, $action)
    {
        $types = [
            'clone' => Command::DO_CLONE,
            'install' => Command::DO_INSTALL,
            'activate' => Command::DO_ACTIVATE,
            'purge' => Command::DO_PURGE,
            // Build
            'prepare' => Command::DO_PREPARE,
            'build' => Command::DO_BUILD,
            'test' => Command::DO_TEST,
            'result' => Command::DO_RESULT,
        ];

        if ($target instanceof BuildPlan) {
            $breadcrumb = [
                [
                    'url' => route('projects', ['project' => $target->project->id]),
                    'label' => $target->project->name
                ],
                [
                    'url' => route('builds', ['build' => $target->id, 'tab' => 'commands']),
                    'label' => trans('projects.build_plan')
                ],
            ];
        } else {
            $breadcrumb = [
                [
                    'url' => route('projects', ['project' => $target->project->id, 'tab' => 'commands']),
                    'label' => $target->project->name
                ],
                [
                    'url' => route('deployments', ['deployment' => $target->id, 'tab' => 'commands']),
                    'label' => trans('projects.deploy_plan')
                ],
            ];
        }

        return view('dashboard.commands.index', [
            'breadcrumb' => $breadcrumb,
            'title' => trans('commands.'.strtolower($action)),
            'subtitle' => $target->name,
            'targetable' => $target,
            'targetable_type' => get_class($target),
            'targetable_id' => $target->id,
            'action' => $types[$action],
            'commands' => $this->getForTaskStep($target, $types[$action]),
        ]);
    }

    /**
     * Store a newly created command in storage.
     *
     * @param  StoreCommandRequest  $request
     * @return Response
     */
    public function store(StoreCommandRequest $request)
    {
        $fields = $request->only(
            'name',
            'user',
            'targetable_type',
            'targetable_id',
            'script',
            'step',
            'optional',
            'default_on',
            'environments',
            'patterns'
        );

        $targetable_type = array_pull($fields, 'targetable_type');
        $targetable_id = array_pull($fields, 'targetable_id');

        $target = $targetable_type::findOrFail($targetable_id);

        // In project
        if ($targetable_type === 'Piplin\\Models\Project') {
            $this->authorize('manage', $target);
        }

        // Get the current highest command order
        $max = $target->commands()->where('step', $fields['step'])
            ->orderBy('order', 'DESC')
            ->first();

        $order = 0;
        if (isset($max)) {
            $order = $max->order + 1;
        }

        $fields['order'] = $order;

        $environments = null;
        if (isset($fields['environments'])) {
            $environments = $fields['environments'];
            unset($fields['environments']);
        }

        $patterns = null;
        if (isset($fields['patterns'])) {
            $patterns = $fields['patterns'];
            unset($fields['patterns']);
        }

        $command = $target->commands()->create($fields);

        if ($environments) {
            $command->environments()->sync($environments);
        }
        $command->environments; // Triggers the loading

        if ($patterns) {
            $command->patterns()->sync($patterns);
        }
        $command->patterns; // Triggers the loading

        return $command;
    }

    /**
     * Update the specified command in storage.
     *
     * @param  Command              $command
     * @param  StoreCommandRequest  $request
     *
     * @return Response|Command
     */
    public function update(Command $command, StoreCommandRequest $request)
    {
        $fields = $request->only(
            'name',
            'user',
            'script',
            'optional',
            'default_on',
            'environments',
            'patterns'
        );

        $environments = null;
        if (isset($fields['environments'])) {
            $environments = $fields['environments'];
            unset($fields['environments']);
        }

        $patterns = null;
        if (isset($fields['patterns'])) {
            $patterns = $fields['patterns'];
            unset($fields['patterns']);
        }

        $command->update($fields);

        if ($environments !== null) {
            $command->environments()->sync($environments);
        }

        if ($patterns !== null) {
            $command->patterns()->sync($patterns);
        }

        $command->environments; // Triggers the loading
        $command->patterns; // Triggers the loading

        return $command;
    }

    /**
     * Re-generates the order for the supplied commands.
     *
     * @param  Request  $request
     * @return Response|array
     */
    public function reorder(Request $request)
    {
        $order = 0;

        foreach ($request->get('commands') as $command_id) {
            $command = Command::findOrFail($command_id);
            $command->update([
                'order' => $order,
            ]);

            $order++;
        }

        return [
            'success' => true,
        ];
    }

    /**
     * Remove the specified command from storage.
     *
     * @param  Command  $command
     * @return Response|array
     */
    public function destroy(Command $command)
    {
        $command->delete();

        return [
            'success' => true,
        ];
    }

    /**
     * Get's the commands in a specific step.
     *
     * @param  Project  $target
     * @param  int      $step
     *
     * @return string
     */
    protected function getForTaskStep($target, $step)
    {
        $with = $target instanceof BuildPlan ? ['patterns'] : ['environments'];

        return $target->commands()
            ->with($with)
            ->whereIn('step', [$step - 1, $step + 1])
            ->orderBy('order', 'asc')
            ->get()->toJson(); // Because CommandPresenter toJson() is not working in the view
    }
}
