<?php

namespace Martianatwork\FilamentphpAutoResource;

use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Tapp\FilamentValueRangeFilter\Filters\ValueRangeFilter;

class BaseAutoRelationManager extends AutoRelationManager
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

    public function getFilters(): array
    {
        $arr = [];
        foreach (static::getEnumDictionary() as $key => $columns) {
            $arr[] = SelectFilter::make($key)->options($columns);
        }

        return $arr;
    }

    public function form(Form $form): Form
    {
        $schema = FormGenerator::make(
            modelClass: $this->getRelationship()->getModel()::class,
            exceptColumns: $this->getExceptRelationshipColumns(),
            overwriteColumns: $this->getColumnsOverwriteMapped('form'),
            enumDictionary: static::getEnumDictionary(),
            relationManagerView: true,
        );
        $casts = (new ($this->getRelationship()->getModel()::class))->getCasts();
        $items = collect($schema);
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
        $items = $items->merge(
            array_map(function ($arr) use ($casts) {
                $name = $arr->getName();
                if (in_array($name, ['password'])) {
                    $arr->password()
                        ->revealable()
                        ->autocomplete(false);
                }
                if (in_array($name, array_keys($casts)) && in_array($casts[$name], ['bool', 'boolean'])) {
                    return Checkbox::make($name)
                        ->required(false);
                }
                if (in_array($name, array_keys($casts)) && in_array($casts[$name], ['datetime', 'timestamp'])) {
                    return DateTimePicker::make($name)
                        ->required($arr->isRequired());
                }
                if (Str::endsWith($name, '_at')) {
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
                if (method_exists($arr, 'getMaxLength')) {
                    $arr->maxLength($arr->getMaxLength() == 0 ? 256 : $arr->getMaxLength());
                }
                if (Str::of($name)->lower()->contains('image')) {
                    return FileUpload::make($name)
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
                if ($name === 'description') {
                    return Textarea::make('description')
                        ->required($arr->isRequired())
                        ->maxLength($arr->getMaxLength())
                        ->columns(4);
                }
                if ($name === 'status') {
                    if (method_exists($arr, 'getOptions') && in_array('draft', $arr->getOptions())) {
                        $arr->default('draft');
                    }
                }
                if ($name === 'currency') {
                    if (method_exists($arr, 'getOptions') && in_array('Rupees', $arr->getOptions())) {
                        $arr->default('inr');
                    }
                }

                return $arr;
            }, $items->all())
        )
            ->filter()
            ->except(
                array_merge(['createdAt', 'id', 'updatedAt'], static::$hiddenColumns)
            );

        return $form
            ->schema($items->all())
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);
        $filters = [];
        foreach (static::getEnumDictionary() as $item => $value) {
            $filters[] = SelectFilter::make($item)->options($value);
        }
        $casts = (new ($this->getRelationship()->getModel()::class))->getCasts();

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
        $fo = $fo->except(
            array_merge(['createdAt', 'updatedAt', 'id'])
        )->all();
        $filters = array_merge($filters, static::getFilters());

        return $table->columns($fo)->filters($filters);
    }
}
