<?php

namespace Martianatwork\FilamentphpAutoResource;

use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Tapp\FilamentValueRangeFilter\Filters\ValueRangeFilter;

class BaseAutoResource extends AutoResource
{
    protected static array $hiddenColumns = [];

    protected static array $relationDictionary = [];

    protected static array $defaultSearchableColumns = [
        'title', 'name', 'description',
    ];

    public static function getRelationshipDictionary(): array
    {
        return static::$relationDictionary;
    }

    public static function getEnumDictionary(): array
    {
        return static::$enumDictionary;
    }

    public static function getFilters(): array
    {
        $arr = [];
        foreach (static::$enumDictionary as $key => $columns) {
            $arr[] = SelectFilter::make($key)->options($columns);
        }

        return $arr;
    }

    public static function form(Form $form): Form
    {
        $fo = FormGenerator::make(
            modelClass: static::getModel(),
            overwriteColumns: static::getColumnsOverwriteMapped('form'),
            enumDictionary: static::$enumDictionary,
        );
        $casts = (new (static::getModel()))->getCasts();
        $fo = array_map(function ($ar) use ($casts) {
            $items = collect($ar->getChildComponents());
            foreach (static::getRelationshipDictionary() as $item => $value) {
                $items = $items->put($item,
                    Select::make($item)
                        ->searchable($value['searchable'] ?? false)
                        ->multiple($value['multiple'] ?? false)
                        ->preload($value['preload'] ?? false)
                        ->required($value['required'] ?? false)
                        ->relationship(name: $value['relationship']['name'], titleAttribute: $value['relationship']['title'])
                );
            }
            $items = $items->map(function ($arr) use ($casts) {
                $name = $arr->getName();
                if (in_array($name, array_keys($casts)) && in_array($casts[$name], ['bool', 'boolean'])) {
                    return Toggle::make($name)
                        ->required(false);
                }
                if (in_array($name, array_keys($casts)) && in_array($casts[$name], ['datetime', 'timestamp'])) {
                    return DateTimePicker::make($name)
                        ->required($arr->isRequired());
                }
                if (in_array($name, array_keys($casts)) && in_array($casts[$name], ['date'])) {
                    return DatePicker::make($name)
                        ->required($arr->isRequired());
                }
                if (in_array($name, array_keys($casts)) && in_array($casts[$name], [TimeCast::class])) {
                    return TimePicker::make($name)
                        ->required($arr->isRequired());
                }
                if (in_array($arr->getName(), ['password'])) {
                    $arr->password()
                        ->revealable()
                        ->autocomplete(false);
                }
                if (method_exists($arr, 'getMaxLength')) {
                    $arr->maxLength($arr->getMaxLength() == 0 ? 256 : $arr->getMaxLength());
                }
                if (Str::of($arr->getName())->lower()->contains('image')) {
                    return FileUpload::make($arr->getName())
                        ->directory(Str::of($name)->snake())
                        ->saveUploadedFileUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                            try {
                                if (! $file->exists()) {
                                    return null;
                                }
                            } catch (UnableToCheckFileExistence $exception) {
                                return null;
                            }

                            if (
                                $component->shouldMoveFiles() &&
                                ($component->getDiskName() == (fn (): string => $component->disk)->call($file))
                            ) {
                                $newPath = trim($component->getDirectory().'/'.$component->getUploadedFileNameForStorage($file), '/');

                                $component->getDisk()->move((fn (): string => $component->path)->call($file), $newPath);

                                return $newPath;
                            }

                            $storeMethod = $component->getVisibility() === 'public' ? 'storePubliclyAs' : 'storeAs';
                            $name = $component->getUploadedFileNameForStorage($file);
                            $uploaded = $file->{$storeMethod}(
                                $component->getDirectory(),
                                $name,
                                $component->getDiskName(),
                            );

                            return Storage::disk($component->getDiskName())->url("{$component->getDirectory()}/{$name}");
                        })
                        ->image();
                }
                if ($arr->getName() === 'description') {
                    return Textarea::make('description')
                        ->required($arr->isRequired())
                        ->maxLength($arr->getMaxLength())
                        ->columns(4);
                }
                if ($arr->getName() === 'status') {
                    if (method_exists($arr, 'getOptions') && in_array('draft', $arr->getOptions())) {
                        $arr->default('draft');
                    }
                    if (method_exists($arr, 'getOptions') && in_array('DRAFT', $arr->getOptions())) {
                        $arr->default('DRAFT');
                    }
                }
                if ($arr->getName() === 'currency') {
                    if (method_exists($arr, 'getOptions') && in_array('Rupees', $arr->getOptions())) {
                        $arr->default('inr');
                    }
                }

                return $arr;
            });
            $overrForm = collect(static::getColumnsOverwrite()['form']);
            $items = $items->map(function ($item) use ($overrForm) {
                return $overrForm->first(function ($item2) use ($item) {
                    return $item2->getName() === $item->getName();
                }) ?? $item;
            });
            $ar->schema($items
                ->filter()
                ->except(
                    array_merge(['createdAt', 'updatedAt'], static::$hiddenColumns)
                )->all());

            return $ar;
        }, $fo);

        return $form
            ->schema($fo)
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        $table = parent::table($table);
        $filters = [];
        foreach (static::$enumDictionary as $item => $value) {
            $filters[] = SelectFilter::make($item)->options($value);
        }
        $casts = (new (static::getModel()))->getCasts();

        $fo = collect();
        //        foreach (static::getRelationshipDictionary() as $item => $value) {
        //            $fo->add(
        //                TextColumn::make($value['relationship']['name'].'.'. $value['relationship']['title'])
        //                    ->searchable($value['searchable'] ?? false)
        //            );
        //        }
        $arr = array_map(function ($ar) use ($table, &$filters, $casts) {
            $name = $ar->getName();
            $ar->table($table)->searchable(in_array($ar->getName(), static::$defaultSearchableColumns));
            if (in_array($name, array_keys($casts)) && in_array($casts[$name], ['bool', 'boolean'])) {
                return ToggleColumn::make($name);
            }
            if ($ar->isNumeric()) {
                $filters[] = ValueRangeFilter::make($ar->getName())
                    ->currencyCode('INR')
                    ->currencyInSmallestUnit(false)
                    ->currency(in_array($ar->getName(), ['amount', 'price']));
            }
            if (in_array($ar->getName(), ['amount', 'price'])) {
                $ar->money('INR');
            }
            if (in_array($ar->getName(), array_keys(static::getRelationshipDictionary()))) {
                $relation = static::getRelationshipDictionary()[$ar->getName()]['relationship'];
                $ar->name($relation['name'].'.'.$relation['title']);
            }

            return $ar;
        }, $table->getColumns());
        $fo = $fo->merge($arr);
        $overrForm = collect(static::getColumnsOverwrite()['table']);
        $fo = $fo->map(function ($item) use ($overrForm) {
            return $overrForm->first(function ($item2) use ($item) {
                return $item2->getName() === $item->getName();
            }) ?? $item;
        });
        $fo = $fo->except(
            array_merge(['createdAt', 'updatedAt', 'id'])
        )->all();
        $filters = array_merge($filters, static::getFilters());

        return $table->columns($fo)->filters($filters);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $schema = InfolistGenerator::make(
            modelClass: static::getModel(),
            overwriteColumns: static::getColumnsOverwriteMapped('infolist'),
            enumDictionary: static::$enumDictionary,
        );
        $schema = array_map(function ($ar) {
            $newAr = array_map(function ($arr) {
                $items = collect($arr->getChildComponents());
                $items = $items->map(function ($arr) {
                    $name = $arr->getName();
                    if (in_array($name, array_keys(static::getRelationshipDictionary()))) {
                        $relation = static::getRelationshipDictionary()[$arr->getName()]['relationship'];
                        $arr->name($relation['name'].'.'.$relation['title']);
                    }
                    if (in_array($name, ['password'])) {
                        $arr->hidden();
                    }

                    return $arr;
                });
                $overrForm = collect(static::getColumnsOverwrite()['infolist']);
                $items = $items->map(function ($item) use ($overrForm) {
                    return $overrForm->first(function ($item2) use ($item) {
                        return $item2->getName() === $item->getName();
                    }) ?? $item;
                });
                $arr->schema($items
                    ->filter()
                    ->except(
                        array_merge(['createdAt', 'updatedAt'], static::$hiddenColumns)
                    )->all());

                return $arr;
                //                return array_map(function ($arra) {
                //
                //                }, $arr->getChildComponents());
            }, $ar->getChildComponents());
            $ar->schema($newAr);

            return $ar;
        }, $schema);

        return $infolist
            ->schema($schema)
            ->columns(3);
    }
}
