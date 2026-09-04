<?php

namespace App\Support;

use App\Enums\DevLogCategory;
use App\Enums\DevLogStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectUserRole;
use App\Enums\TaskPriority;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The semantic input contract shared by Filament, the API, and the extension.
 *
 * Presentation adapters may add layout details, but field names, ordering,
 * validation, defaults, options, and editability belong here.
 */
final class InputSchemaRegistry
{
    public const int VERSION = 1;

    /**
     * Return the JSON-safe contract consumed by the browser extension.
     *
     * @return array<string, mixed>
     */
    public static function contract(): array
    {
        return [
            'version' => self::VERSION,
            'models' => self::serializeDefinitions(self::models()),
            'relations' => self::serializeDefinitions(self::relations()),
            'actions' => self::actions(),
            'filters' => self::serializeFilters(self::filters()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function model(string $model): array
    {
        return self::models()[$model] ?? throw new \InvalidArgumentException("Unknown input model [{$model}].");
    }

    /**
     * @return array<string, mixed>
     */
    public static function relation(string $relation): array
    {
        return self::relations()[$relation] ?? throw new \InvalidArgumentException("Unknown input relation [{$relation}].");
    }

    /**
     * @return array<string, mixed>
     */
    public static function field(string $definition, string $field): array
    {
        $fields = isset(self::models()[$definition])
            ? self::model($definition)['fields']
            : self::relation($definition)['fields'];

        return $fields[$field] ?? throw new \InvalidArgumentException("Unknown input field [{$definition}.{$field}].");
    }

    /**
     * Return Laravel validation rules for a model operation.
     *
     * Resource-specific controllers still add cross-field and authorization
     * rules (for example project ownership and state transitions).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $model, string $operation, ?int $ignoreId = null): array
    {
        $definition = isset(self::models()[$model])
            ? self::model($model)
            : self::relation($model);
        $rules = [];

        foreach ($definition['fields'] as $name => $field) {
            $requestName = $field['request_name'] ?? $name;
            $editableOperation = in_array($operation, ['update', 'attach_update'], true) ? 'edit' : $operation;

            if (($field['display_only'] ?? false) || ! in_array($editableOperation, $field['editable_on'] ?? ['create', 'edit'], true)) {
                continue;
            }

            $fieldRules = [];
            $required = in_array($editableOperation, $field['required_on'] ?? [], true);

            $partial = in_array($operation, ['update', 'attach_update'], true);

            if ($partial) {
                $fieldRules[] = 'sometimes';
                if ($field['nullable'] ?? true) {
                    $fieldRules[] = 'nullable';
                }
            } elseif ($required) {
                $fieldRules[] = 'required';
            } elseif ($field['nullable'] ?? true) {
                $fieldRules[] = 'nullable';
            } else {
                $fieldRules[] = 'sometimes';
            }

            $type = $field['type'];
            if ($type === 'file') {
                $upload = $field['upload'] ?? [];
                if ($field['multiple'] ?? false) {
                    $fieldRules[] = 'array';
                    $rules[$requestName.'.*'] = [
                        'file',
                        'max:'.($upload['max_size_kb'] ?? 10240),
                    ];
                } else {
                    $fieldRules[] = 'file';
                    $fieldRules[] = 'max:'.($upload['max_size_kb'] ?? 10240);
                }
            } elseif ($type === 'number') {
                $fieldRules[] = 'numeric';
                if (array_key_exists('min', $field)) {
                    $fieldRules[] = 'min:'.$field['min'];
                }
            } elseif ($type === 'date') {
                $fieldRules[] = 'date_format:Y-m-d';
            } elseif ($type === 'datetime') {
                $fieldRules[] = 'date';
            } elseif ($type === 'email') {
                $fieldRules[] = 'email';
            } elseif ($type === 'url') {
                $fieldRules[] = 'url';
            } elseif (in_array($type, ['text', 'tel', 'password', 'textarea', 'select', 'multi_select'], true)) {
                $fieldRules[] = $type === 'multi_select' ? 'array' : (($field['value_type'] ?? null) === 'integer' ? 'integer' : 'string');
            }

            if (isset($field['max_length'])) {
                $fieldRules[] = 'max:'.$field['max_length'];
            }

            if (($field['unique'] ?? false) === true) {
                $unique = Rule::unique($definition['table'] ?? $name, $name);
                if ($ignoreId !== null) {
                    $unique->ignore($ignoreId);
                }
                $fieldRules[] = $unique;
            }

            if (isset($field['enum'])) {
                $fieldRules[] = Rule::enum($field['enum']);
            }

            if (isset($field['exists']) && ! ($field['multiple'] ?? false)) {
                $fieldRules[] = 'exists:'.$field['exists'];
            }

            if (($field['multiple'] ?? false) && isset($field['exists'])) {
                $rules[$requestName.'.*'] = ['integer', 'exists:'.$field['exists']];
            }

            if ($type === 'toggle') {
                $fieldRules[] = 'boolean';
            }

            $rules[$requestName] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Generate the same slug fallback used by the Filament create page.
     */
    public static function generatedProjectSlug(string $name): string
    {
        return Str::slug($name).'-'.Str::lower(Str::random(6));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function models(): array
    {
        return [
            'users' => [
                'table' => 'users',
                'fields' => [
                    'name' => self::text('姓名', requiredOn: ['create', 'edit'], maxLength: 255),
                    'email' => self::text('邮箱', type: 'email', requiredOn: ['create', 'edit'], unique: true),
                    'wechat' => self::text('微信', maxLength: 255),
                    'phone' => self::text('手机号码', type: 'tel', maxLength: 255),
                    'remark' => self::textarea('备注（仅后台可见）', rows: 2, helperText: '仅管理员可见'),
                    'password' => self::text('初始密码', type: 'password', requiredOn: ['create'], revealable: true),
                ],
            ],
            'projects' => [
                'table' => 'projects',
                'fields' => [
                    'name' => self::text('项目名称', requiredOn: ['create', 'edit'], maxLength: 255),
                    'slug' => self::text('项目标识', maxLength: 255, unique: true, helperText: '留空将根据项目名自动生成'),
                    'description' => self::textarea('项目描述', rows: 4),
                    'members' => self::relationField('分配客户', '/api/users', multiple: true, searchable: true, exists: 'users,id'),
                    'status' => self::select('项目状态', ProjectStatus::class, requiredOn: ['create', 'edit'], default: 'active'),
                    'amount' => self::number('项目总额', min: 0, prefix: '¥'),
                    'paid_amount' => self::number('已付金额', min: 0, default: 0, prefix: '¥'),
                    'unpaid_amount' => [
                        'type' => 'computed',
                        'label' => '未付金额',
                        'display_only' => true,
                        'depends_on' => ['amount', 'paid_amount'],
                    ],
                    'deadline' => self::date('截止日期'),
                    'repo_url' => self::text('代码仓库', type: 'url', maxLength: 2048, placeholder: 'https://github.com/...'),
                    'remark' => self::textarea('备注', rows: 3),
                ],
            ],
            'tasks' => [
                'table' => 'tasks',
                'fields' => [
                    'project_id' => self::relationField('项目', '/api/projects', requiredOn: ['create', 'edit'], exists: 'projects,id'),
                    'title' => self::text('任务标题', requiredOn: ['create', 'edit'], maxLength: 255),
                    'description' => self::textarea('任务描述', rows: 6),
                    'attachments' => self::file('附件', multiple: true, upload: [
                        'max_files' => 10,
                        'max_size_kb' => 10240,
                        'disk' => 'public',
                        'directory' => 'task-attachments',
                        'previewable' => false,
                    ]),
                    'priority' => self::select('优先级', TaskPriority::class, requiredOn: ['create', 'edit'], default: 'medium'),
                    'status' => [
                        'type' => 'computed',
                        'label' => '状态',
                        'display_only' => true,
                        'default' => 'pending',
                        'enum' => \App\Enums\TaskStatus::class,
                        'state_machine' => 'tasks',
                    ],
                    'reject_reason' => [
                        'type' => 'textarea',
                        'label' => '拒绝原因',
                        'rows' => 2,
                        'display_only' => true,
                        'visible_when' => ['field' => 'status', 'equals' => 'rejected'],
                    ],
                ],
            ],
            'dev_logs' => [
                'table' => 'dev_logs',
                'fields' => [
                    'project_id' => self::relationField('项目', '/api/projects', requiredOn: ['create', 'edit'], exists: 'projects,id'),
                    'date' => self::date('日期', requiredOn: ['create', 'edit'], default: ['kind' => 'today']),
                    'status' => self::select('状态', DevLogStatus::class, requiredOn: ['create', 'edit'], default: 'in_progress'),
                    'category' => self::select('分类', DevLogCategory::class, requiredOn: ['create', 'edit'], default: 'agent_independent'),
                    'content' => self::textarea('记录内容', rows: 4, requiredOn: ['create', 'edit']),
                ],
            ],
            'dev_log_updates' => [
                'table' => 'dev_log_updates',
                'fields' => [
                    'dev_log_id' => self::relationField('开发日志', '/api/dev-logs', requiredOn: ['create', 'edit'], exists: 'dev_logs,id'),
                    'update' => self::textarea('更新内容', rows: 6, requiredOn: ['create', 'edit']),
                ],
            ],
            'issues' => [
                'table' => 'issues',
                'fields' => [
                    'project_id' => self::relationField('项目', '/api/projects', requiredOn: ['create', 'edit'], exists: 'projects,id'),
                    'title' => self::text('问题标题', requiredOn: ['create', 'edit'], maxLength: 255),
                    'description' => self::textarea('描述/详情', rows: 3),
                    'attachment_path' => self::file('附件/截图', requestName: 'attachment', upload: [
                        'max_files' => 1,
                        'max_size_kb' => 10240,
                        'disk' => 'local',
                        'directory' => 'issue-attachments',
                        'accept' => ['image/*', 'application/pdf', 'application/zip', 'application/x-zip-compressed', 'text/plain'],
                    ]),
                    'severity' => self::select('严重程度', IssueSeverity::class, requiredOn: ['create', 'edit'], default: 'normal'),
                    'status' => [
                        'type' => 'computed',
                        'label' => '状态',
                        'display_only' => true,
                        'default' => 'open',
                        'enum' => IssueStatus::class,
                        'state_machine' => 'issues',
                    ],
                ],
            ],
            'contracts' => [
                'table' => 'contracts',
                'fields' => [
                    'project_id' => self::relationField('项目', '/api/projects', requiredOn: ['create', 'edit'], exists: 'projects,id'),
                    'name' => self::text('文件名', requiredOn: ['create', 'edit'], maxLength: 255),
                    'file_path' => self::file('合同文件', requestName: 'file', requiredOn: ['create'], upload: [
                        'max_files' => 1,
                        'max_size_kb' => 51200,
                        'disk' => 'local',
                        'directory' => 'contracts',
                        'preserve_filenames' => true,
                        'retain_existing_on_edit' => true,
                    ]),
                ],
            ],
            'accounts' => [
                'table' => 'accounts',
                'fields' => [
                    'project_id' => self::relationField('所属项目', '/api/projects', requiredOn: ['create', 'edit'], exists: 'projects,id'),
                    'website_name' => self::text('网站名称', requiredOn: ['create', 'edit'], maxLength: 255),
                    'login_url' => self::text('登录地址', type: 'url', requiredOn: ['create', 'edit'], maxLength: 2048, placeholder: 'https://example.com/login'),
                    'username' => self::text('用户名', requiredOn: ['create', 'edit'], maxLength: 255),
                    'password' => self::text('密码', type: 'password', requiredOn: ['create', 'edit'], maxLength: 255, revealable: true),
                    'note' => self::textarea('备注', rows: 3),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function relations(): array
    {
        return [
            'project_members' => [
                'parent' => 'projects',
                'fields' => [
                    'user_id' => self::relationField('客户', '/api/users', requiredOn: ['create'], exists: 'users,id'),
                    'role' => self::select('角色', ProjectUserRole::class, default: 'member'),
                    'can_view_price' => self::toggle('允许查看项目金额', default: false),
                ],
            ],
            'user_projects' => [
                'parent' => 'users',
                'fields' => [
                    'project_id' => self::relationField('项目', '/api/projects', requiredOn: ['create'], exists: 'projects,id'),
                    'role' => self::select('角色', ProjectUserRole::class, default: 'member'),
                    'can_view_price' => self::toggle('允许查看项目金额', default: false),
                ],
            ],
            'project_payments' => [
                'parent' => 'projects',
                'fields' => [
                    'amount' => self::number('金额', requiredOn: ['create', 'edit'], min: 0.01, prefix: '¥'),
                    'date' => self::date('付款日期', requiredOn: ['create', 'edit'], default: ['kind' => 'today']),
                    'remark' => self::textarea('备注（客户可见）', maxLength: 65535),
                ],
            ],
            'project_invitations' => [
                'parent' => 'projects',
                'fields' => [
                    'email' => self::text('客户邮箱', type: 'email', requiredOn: ['create'], maxLength: 255),
                    'expires_at' => self::datetime('过期时间', requiredOn: ['create'], default: ['kind' => 'now_plus_days', 'days' => 7]),
                ],
            ],
            'task_comments' => [
                'parent' => 'tasks',
                'fields' => [
                    'body' => self::textarea('发表评论', requiredOn: ['create'], maxLength: 10000),
                ],
            ],
            'dev_log_batch' => [
                'parent' => 'projects',
                'repeatable' => true,
                'fields' => [
                    'date' => self::date('日期', requiredOn: ['create'], default: ['kind' => 'today']),
                    'status' => self::select('状态', DevLogStatus::class, requiredOn: ['create'], default: 'in_progress'),
                    'category' => self::select('分类', DevLogCategory::class, requiredOn: ['create'], default: 'agent_independent'),
                    'content' => self::textarea('记录内容', requiredOn: ['create'], rows: 4),
                ],
            ],
        ];
    }

    /**
     * Administrator actions exposed by the state-machine adapters.
     * Customer-only task transitions intentionally do not appear here.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function actions(): array
    {
        return [
            'tasks' => [
                ['name' => 'confirm', 'label' => '确认', 'from' => ['pending'], 'to' => 'confirmed'],
                ['name' => 'reject', 'label' => '拒绝', 'from' => ['pending'], 'to' => 'rejected', 'requires' => ['reject_reason']],
                ['name' => 'start', 'label' => '开始处理', 'from' => ['confirmed'], 'to' => 'in_progress'],
                ['name' => 'restart', 'label' => '返工处理', 'from' => ['changes_requested'], 'to' => 'in_progress'],
                ['name' => 'complete', 'label' => '标记完成', 'from' => ['in_progress'], 'to' => 'done'],
            ],
            'issues' => [
                ['name' => 'start', 'label' => '开始处理', 'from' => ['open'], 'to' => 'in_progress'],
                ['name' => 'resolve', 'label' => '标记解决', 'from' => ['in_progress'], 'to' => 'resolved'],
                ['name' => 'close', 'label' => '关闭', 'from' => ['resolved'], 'to' => 'closed'],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function filters(): array
    {
        return [
            'users' => [
                ['name' => 'search', 'type' => 'search', 'label' => '搜索姓名 / 邮箱 / 微信 / 手机号'],
            ],
            'projects' => [
                ['name' => 'search', 'type' => 'search', 'label' => '搜索项目名称 / slug'],
                ['name' => 'status', 'type' => 'select', 'label' => '项目状态', 'enum' => ProjectStatus::class],
            ],
            'tasks' => [
                ['name' => 'project_id', 'type' => 'relation', 'label' => '项目', 'endpoint' => '/api/projects'],
                ['name' => 'status', 'type' => 'select', 'label' => '任务状态', 'enum' => \App\Enums\TaskStatus::class],
                ['name' => 'priority', 'type' => 'select', 'label' => '优先级', 'enum' => TaskPriority::class],
            ],
            'dev_logs' => [
                ['name' => 'project_id', 'type' => 'relation', 'label' => '项目', 'endpoint' => '/api/projects'],
                ['name' => 'status', 'type' => 'select', 'label' => '状态', 'enum' => DevLogStatus::class],
                ['name' => 'category', 'type' => 'select', 'label' => '分类', 'enum' => DevLogCategory::class],
            ],
            'dev_log_updates' => [
                ['name' => 'dev_log_id', 'type' => 'relation', 'label' => '开发日志', 'endpoint' => '/api/dev-logs'],
            ],
            'issues' => [
                ['name' => 'project_id', 'type' => 'relation', 'label' => '项目', 'endpoint' => '/api/projects'],
                ['name' => 'severity', 'type' => 'select', 'label' => '严重程度', 'enum' => IssueSeverity::class],
                ['name' => 'status', 'type' => 'select', 'label' => '问题状态', 'enum' => IssueStatus::class],
            ],
            'contracts' => [
                ['name' => 'project_id', 'type' => 'relation', 'label' => '项目', 'endpoint' => '/api/projects'],
            ],
            'accounts' => [
                ['name' => 'project_id', 'type' => 'relation', 'label' => '项目', 'endpoint' => '/api/projects'],
                ['name' => 'search', 'type' => 'search', 'label' => '搜索网站名称 / 用户名'],
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @return array<string, array<string, mixed>>
     */
    private static function serializeDefinitions(array $definitions): array
    {
        foreach ($definitions as $key => $definition) {
            foreach ($definition['fields'] as $fieldName => $field) {
                $definitions[$key]['fields'][$fieldName]['required_on'] = $field['required_on'] ?? [];
                $definitions[$key]['fields'][$fieldName]['editable_on'] = $field['editable_on'] ?? ['create', 'edit'];
                $definitions[$key]['fields'][$fieldName]['required_on_create'] = in_array('create', $field['required_on'] ?? [], true);
                $definitions[$key]['fields'][$fieldName]['required_on_edit'] = in_array('edit', $field['required_on'] ?? [], true);
                $definitions[$key]['fields'][$fieldName]['read_only'] = (bool) ($field['display_only'] ?? false);
                $definitions[$key]['fields'][$fieldName]['transport_key'] = $field['request_name'] ?? $fieldName;
                $definitions[$key]['fields'][$fieldName]['api_transport_key'] = $field['request_name'] ?? $fieldName;
                $definitions[$key]['fields'][$fieldName]['default'] ??= null;
                $definitions[$key]['fields'][$fieldName]['limits'] = array_filter([
                    'min' => $field['min'] ?? null,
                    'max_length' => $field['max_length'] ?? null,
                ], fn ($value): bool => $value !== null);
                if (isset($field['visible_when'])) {
                    $definitions[$key]['fields'][$fieldName]['visibility'] = $field['visible_when'];
                }
                if (isset($field['enum'])) {
                    $definitions[$key]['fields'][$fieldName]['options'] = self::enumOptions($field['enum']);
                    unset($definitions[$key]['fields'][$fieldName]['enum']);
                }
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $filters
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function serializeFilters(array $filters): array
    {
        foreach ($filters as $definition => $items) {
            foreach ($items as $index => $filter) {
                if (isset($filter['enum'])) {
                    $filters[$definition][$index]['options'] = self::enumOptions($filter['enum']);
                    unset($filters[$definition][$index]['enum']);
                }
            }
        }

        return $filters;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private static function enumOptions(string $enum): array
    {
        return array_map(
            fn (\BackedEnum $case): array => [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : $case->name,
            ],
            $enum::cases(),
        );
    }

    /**
     * @param array<int, string> $requiredOn
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function text(
        string $label,
        string $type = 'text',
        array $requiredOn = [],
        ?int $maxLength = null,
        bool $unique = false,
        ?string $helperText = null,
        ?string $placeholder = null,
        bool $revealable = false,
        array $extra = [],
    ): array {
        return array_filter([
            'type' => $type,
            'label' => $label,
            'required_on' => $requiredOn,
            'nullable' => $requiredOn === [],
            'max_length' => $maxLength,
            'unique' => $unique,
            'helper_text' => $helperText,
            'placeholder' => $placeholder,
            'revealable' => $revealable,
        ] + $extra, fn ($value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $requiredOn
     * @return array<string, mixed>
     */
    private static function textarea(string $label, array $requiredOn = [], int $rows = 3, ?int $maxLength = null, ?string $helperText = null): array
    {
        return array_filter([
            'type' => 'textarea',
            'label' => $label,
            'required_on' => $requiredOn,
            'nullable' => $requiredOn === [],
            'rows' => $rows,
            'max_length' => $maxLength,
            'helper_text' => $helperText,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $requiredOn
     * @return array<string, mixed>
     */
    private static function select(string $label, string $enum, array $requiredOn = [], mixed $default = null): array
    {
        return array_filter([
            'type' => 'select',
            'label' => $label,
            'required_on' => $requiredOn,
            'nullable' => $requiredOn === [],
            'enum' => $enum,
            'default' => $default,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $requiredOn
     * @return array<string, mixed>
     */
    private static function number(string $label, array $requiredOn = [], float|int|null $min = null, mixed $default = null, ?string $prefix = null): array
    {
        return array_filter([
            'type' => 'number',
            'label' => $label,
            'required_on' => $requiredOn,
            'nullable' => $requiredOn === [],
            'min' => $min,
            'default' => $default,
            'prefix' => $prefix,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $requiredOn
     * @return array<string, mixed>
     */
    private static function date(string $label, array $requiredOn = [], mixed $default = null): array
    {
        return array_filter([
            'type' => 'date',
            'label' => $label,
            'required_on' => $requiredOn,
            'nullable' => $requiredOn === [],
            'default' => $default,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $requiredOn
     * @return array<string, mixed>
     */
    private static function datetime(string $label, array $requiredOn = [], mixed $default = null): array
    {
        return array_filter([
            'type' => 'datetime',
            'label' => $label,
            'required_on' => $requiredOn,
            'nullable' => $requiredOn === [],
            'default' => $default,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $requiredOn
     * @param array<string, mixed> $upload
     * @return array<string, mixed>
     */
    private static function file(string $label, bool $multiple = false, ?string $requestName = null, array $requiredOn = [], array $upload = []): array
    {
        return array_filter([
            'type' => 'file',
            'label' => $label,
            'required_on' => $requiredOn,
            'nullable' => $requiredOn === [],
            'multiple' => $multiple,
            'request_name' => $requestName,
            'upload' => $upload,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $requiredOn
     * @return array<string, mixed>
     */
    private static function relationField(string $label, string $endpoint, bool $multiple = false, bool $searchable = false, array $requiredOn = [], ?string $exists = null): array
    {
        return array_filter([
            'type' => $multiple ? 'multi_select' : 'select',
            'label' => $label,
            'required_on' => $requiredOn,
            'nullable' => $requiredOn === [],
            'multiple' => $multiple,
            'value_type' => 'integer',
            'searchable' => $searchable,
            'endpoint' => $endpoint,
            'relationship' => $multiple
                ? ($exists === 'users,id' ? 'members' : null)
                : match ($exists) {
                    'users,id' => 'user',
                    'projects,id' => 'project',
                    'dev_logs,id' => 'devLog',
                    default => null,
                },
            'exists' => $exists,
        ], fn ($value): bool => $value !== null);
    }

    private static function toggle(string $label, mixed $default = null): array
    {
        return array_filter([
            'type' => 'toggle',
            'label' => $label,
            'default' => $default,
        ], fn ($value): bool => $value !== null);
    }
}
