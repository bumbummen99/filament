<?php

namespace Filament\Tables\Concerns;

use Closure;
use Filament\Forms\ComponentContainer;
use Filament\Support\Actions\Exceptions\Hold;
use Filament\Tables\Actions\BulkAction;

/**
 * @property ComponentContainer $mountedTableBulkActionForm
 */
trait HasBulkActions
{
    public $mountedTableBulkAction = null;

    public $mountedTableBulkActionData = [];

    protected array $cachedTableBulkActions;

    public function cacheTableBulkActions(): void
    {
        $actions = BulkAction::configureUsing(
            Closure::fromCallable([$this, 'configureTableBulkAction']),
            fn (): array => $this->getTableBulkActions(),
        );

        $this->cachedTableBulkActions = collect($actions)
            ->mapWithKeys(function (BulkAction $action): array {
                $action->table($this->getCachedTable());

                return [$action->getName() => $action];
            })
            ->toArray();
    }

    protected function configureTableBulkAction(BulkAction $action): void
    {
    }

    public function callMountedTableBulkAction(?string $arguments = null)
    {
        $action = $this->getMountedTableBulkAction();

        if (! $action) {
            return;
        }

        if ($action->isDisabled()) {
            return;
        }

        $form = $this->getMountedTableBulkActionForm();

        if ($action->hasForm()) {
            $action->callBeforeFormValidated();

            $action->formData($form->getState());

            $action->callAfterFormValidated();
        }

        $action->callBefore();

        try {
            $result = $action->call([
                'arguments' => json_decode($arguments, associative: true) ?? [],
                'form' => $form,
            ]);
        } catch (Hold $exception) {
            return;
        }

        try {
            return $action->callAfter() ?? $result;
        } finally {
            $this->mountedTableBulkAction = null;
            $this->selectedTableRecords = [];
            $action->resetFormData();

            $this->dispatchBrowserEvent('close-modal', [
                'id' => static::class . '-table-bulk-action',
            ]);
        }
    }

    public function mountTableBulkAction(string $name, array $selectedRecords)
    {
        $this->mountedTableBulkAction = $name;
        $this->selectedTableRecords = $selectedRecords;

        $action = $this->getMountedTableBulkAction();

        if (! $action) {
            return;
        }

        if ($action->isDisabled()) {
            return;
        }

        $this->cacheForm(
            'mountedTableBulkActionForm',
            fn () => $this->getMountedTableBulkActionForm(),
        );

        if ($action->hasForm()) {
            $action->callBeforeFormFilled();
        }

        app()->call($action->getMountUsing(), [
            'action' => $action,
            'form' => $this->getMountedTableBulkActionForm(),
            'records' => $this->getSelectedTableRecords(),
        ]);

        if ($action->hasForm()) {
            $action->callAfterFormFilled();
        }

        if (! $action->shouldOpenModal()) {
            return $this->callMountedTableBulkAction();
        }

        $this->resetErrorBag();

        $this->dispatchBrowserEvent('open-modal', [
            'id' => static::class . '-table-bulk-action',
        ]);
    }

    public function getCachedTableBulkActions(): array
    {
        return collect($this->cachedTableBulkActions)
            ->filter(fn (BulkAction $action): bool => ! $action->isHidden())
            ->toArray();
    }

    public function getMountedTableBulkAction(): ?BulkAction
    {
        if (! $this->mountedTableBulkAction) {
            return null;
        }

        return $this->getCachedTableBulkAction($this->mountedTableBulkAction);
    }

    public function getMountedTableBulkActionForm(): ?ComponentContainer
    {
        $action = $this->getMountedTableBulkAction();

        if (! $action) {
            return null;
        }

        if ((! $this->isCachingForms) && $this->hasCachedForm('mountedTableBulkActionForm')) {
            return $this->getCachedForm('mountedTableBulkActionForm');
        }

        return $this->makeForm()
            ->schema($action->getFormSchema())
            ->model($this->getTableQuery()->getModel()::class)
            ->statePath('mountedTableBulkActionData');
    }

    protected function getCachedTableBulkAction(string $name): ?BulkAction
    {
        $action = $this->getCachedTableBulkActions()[$name] ?? null;
        $action?->records($this->getSelectedTableRecords());

        return $action;
    }

    protected function getTableBulkActions(): array
    {
        return [];
    }
}
