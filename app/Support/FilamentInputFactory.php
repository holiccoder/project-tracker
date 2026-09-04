<?php

namespace App\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

/**
 * Filament's presentation adapter for the shared input contract.
 */
final class FilamentInputFactory
{
    public static function make(string $definition, string $name): mixed
    {
        $field = InputSchemaRegistry::field($definition, $name);
        $type = $field['type'];

        $component = match ($type) {
            'textarea' => Textarea::make($name)->rows($field['rows'] ?? 3),
            'select', 'multi_select' => Select::make($name),
            'date' => DatePicker::make($name),
            'datetime' => DateTimePicker::make($name),
            'file' => FileUpload::make($name),
            'toggle' => Toggle::make($name),
            'computed' => Placeholder::make($name),
            default => TextInput::make($name),
        };

        if ($type === 'computed') {
            return $component
                ->label($field['label'])
                ->content(function ($record) use ($name, $field): string {
                    $value = $record?->{$name};
                    if (is_object($value) && method_exists($value, 'label')) {
                        return (string) $value->label();
                    }

                    return (string) ($value ?? $field['default'] ?? '');
                });
        }

        $component = $component->label($field['label']);

        if (isset($field['max_length']) && method_exists($component, 'maxLength')) {
            $component = $component->maxLength($field['max_length']);
        }

        if (isset($field['default'])) {
            $component = $component->default(self::defaultValue($field['default']));
        }

        if (($field['required_on'] ?? []) !== []) {
            $component = $component->required(
                fn (string $operation): bool => in_array($operation, $field['required_on'], true),
            );
        }

        if (! empty($field['helper_text']) && method_exists($component, 'helperText')) {
            $component = $component->helperText($field['helper_text']);
        }

        if (! empty($field['placeholder']) && method_exists($component, 'placeholder')) {
            $component = $component->placeholder($field['placeholder']);
        }

        if ($type === 'text' || $type === 'email' || $type === 'tel' || $type === 'url' || $type === 'password') {
            if ($type === 'email') {
                $component = $component->email();
            } elseif ($type === 'tel') {
                $component = $component->tel();
            } elseif ($type === 'url') {
                $component = $component->url();
            } elseif ($type === 'password') {
                $component = $component->password();
                if ($field['revealable'] ?? false) {
                    $component = $component->revealable();
                }
            }
        }

        if ($type === 'number') {
            $component = $component->numeric();
            if (array_key_exists('min', $field)) {
                $component = $component->minValue($field['min']);
            }
            if (isset($field['prefix'])) {
                $component = $component->prefix($field['prefix']);
            }
        }

        if ($type === 'select' && isset($field['enum'])) {
            $component = $component->options($field['enum']);
        }

        if ($type === 'multi_select') {
            $component = $component->multiple();
        }

        if ($type === 'file') {
            $upload = $field['upload'] ?? [];
            if ($field['multiple'] ?? false) {
                $component = $component->multiple();
            }
            if (isset($upload['disk'])) {
                $component = $component->disk($upload['disk']);
            }
            if (isset($upload['directory'])) {
                $component = $component->directory($upload['directory']);
            }
            if (isset($upload['max_files'])) {
                $component = $component->maxFiles($upload['max_files']);
            }
            if (isset($upload['max_size_kb'])) {
                $component = $component->maxSize($upload['max_size_kb']);
            }
            if (($upload['previewable'] ?? true) === false) {
                $component = $component->previewable(false);
            }
            if (($upload['preserve_filenames'] ?? false) === true) {
                $component = $component->preserveFilenames();
            }
            if (isset($upload['accept'])) {
                $component = $component->acceptedFileTypes($upload['accept']);
            }
        }

        return $component;
    }

    private static function defaultValue(mixed $default): mixed
    {
        if (! is_array($default)) {
            return $default;
        }

        return match ($default['kind'] ?? null) {
            'today' => now()->toDateString(),
            'now_plus_days' => now()->addDays((int) ($default['days'] ?? 0)),
            default => null,
        };
    }
}
