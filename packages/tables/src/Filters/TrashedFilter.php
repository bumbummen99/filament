<?php

namespace Filament\Tables\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TrashedFilter extends TernaryFilter
{
    public static function make(string $name = 'trashed'): static
    {
        return parent::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('tables::table.filters.trashed.label'));

        $this->placeholder(__('tables::table.filters.trashed.without_trashed'));

        $this->trueLabel(__('tables::table.filters.trashed.with_trashed'));

        $this->falseLabel(__('tables::table.filters.trashed.only_trashed'));

        $this->queries(
            true: fn ($query) => $query->withTrashed(),
            false: fn ($query) => $query->onlyTrashed(),
            blank: fn ($query) => $query->withoutTrashed(),
        );
    }

    public function applyToBaseQuery(Builder $query, array $data = []): Builder
    {
        $query->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);

        return $query;
    }
}
