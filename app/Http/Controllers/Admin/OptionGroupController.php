<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveOptionGroupRequest;
use App\Models\OptionGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OptionGroupController extends Controller
{
    public function store(SaveOptionGroupRequest $request): RedirectResponse
    {
        return $this->save($request, new OptionGroup);
    }

    public function update(SaveOptionGroupRequest $request, OptionGroup $optionGroup): RedirectResponse
    {
        return $this->save($request, $optionGroup);
    }

    private function save(SaveOptionGroupRequest $request, OptionGroup $group): RedirectResponse
    {
        $data = $request->validated();
        try {
            DB::transaction(function () use ($data, $group): void {
                if ($group->exists) {
                    $group = OptionGroup::query()->lockForUpdate()->findOrFail($group->id);
                    $submittedIds = array_filter(array_column($data['options'], 'id'));
                    $group->options()->whereNotIn('id', $submittedIds)->delete();
                    foreach ($group->options as $option) {
                        $option->update(['name' => '__editing_'.Str::uuid()]);
                    }
                }
                $group->fill(Arr::only($data, ['name', 'selection_type', 'is_active']))->save();
                foreach ($data['options'] as $option) {
                    if (! empty($option['id'])) {
                        $group->options()->whereKey($option['id'])->firstOrFail()->update(Arr::except($option, 'id'));
                    } else {
                        $group->options()->create(Arr::except($option, 'id'));
                    }
                }
                if (mb_strtolower($group->name) === 'sugar level') {
                    $group->products()->newPivotQuery()->update(['is_required' => true]);
                }
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23503' ||
                ($exception->getCode() === '23000' && str_contains($exception->errorInfo[2] ?? '', 'FOREIGN KEY constraint failed'))) {
                throw ValidationException::withMessages(['options' => 'Opsi masih digunakan oleh data lain dan tidak dapat dihapus.']);
            }
            throw $exception;
        }

        return back()->with('success', 'Grup opsi berhasil disimpan.');
    }
}
