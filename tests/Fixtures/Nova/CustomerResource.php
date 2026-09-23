<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Nova;

use Gabrielesbaiz\NovaAjaxSelect\AjaxSelect;
use Gabrielesbaiz\NovaAjaxSelect\Support\AjaxSelectContext;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\City;
use Gabrielesbaiz\NovaAjaxSelect\Tests\Fixtures\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * A three-level chain: province -> city -> zip code.
 */
class CustomerResource extends Resource
{
    public static $model = Customer::class;

    public static $title = 'name';

    public static $search = ['name'];

    public static function uriKey(): string
    {
        return 'customers';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),

            Text::make('Name'),

            Select::make('Province', 'province_id')->options([
                1 => 'Udine',
                2 => 'Trieste',
            ]),

            AjaxSelect::make('City', 'city_id')
                ->parent('province_id')
                ->optionsFromModel(
                    City::class,
                    query: fn (Builder $query, AjaxSelectContext $context) => $query->where('province_id', $context->parent())
                )
                ->labelFrom('city.name'),

            AjaxSelect::make('Zip code', 'zip_code')
                ->parent('city_id')
                ->options(fn (AjaxSelectContext $context) => City::query()
                    ->whereKey($context->parent())
                    ->pluck('zip_code', 'zip_code')),
        ];
    }
}
