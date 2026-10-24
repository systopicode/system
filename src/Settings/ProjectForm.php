<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Panels\PanelNode;

/**
 * Page Settings and Media Settings: one table per group in the file.
 */
final class ProjectForm
{
    public static function render(string $which): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $title = $which === 'media' ? 'Media Settings' : 'Page Settings';
        $filename = ProjectFile::FILES[$which] ?? '';
        $path = ProjectFile::path($which);
        echo '<div class="projectFiles">';
        echo '<h1>' . $e($title) . '</h1>';
        echo '<p class="tagsHint">Leave a field to write it into ' . $e($filename) . '. Drag the handle to change the order.</p>';
        echo \message::flush();
        if ($path === null) {
            echo '<p>Missing ' . $e($filename) . '.</p></div>';
            return;
        }
        $sections = [];
        foreach (ProjectFile::groups($path) as $group) {
            $name = $group->category !== '' ? $group->category : $title;
            $sections[$name][] = $group;
        }
        foreach ($sections as $name => $groups) {
            $scalars = array_values(array_filter($groups, static fn(Group $group): bool => $group->shape === 'scalar'));
            $collections = array_values(array_filter($groups, static fn(Group $group): bool => $group->shape !== 'scalar'));
            $only = $scalars === [] && count($collections) === 1;
            echo '<section class="widget">';
            echo '<header><h2>' . $e($name) . '</h2>';
            if ($only) {
                self::addButton($which, $collections[0]);
            }
            echo '</header>';
            if ($scalars !== []) {
                $languages = null;
                foreach ($collections as $group) {
                    if ($group->id === 'define:LANGUAGES') {
                        $languages = $group;
                    }
                }
                self::scalarTable($which, $scalars, $languages);
            }
            foreach ($collections as $group) {
                if (!$only) {
                    $heading = self::human($group->label);
                    $repeat = strcasecmp($heading, (string) $name) === 0 && count($collections) === 1;
                    echo '<div class="projectBar">';
                    if (!$repeat) {
                        echo '<h3>' . $e($heading) . '</h3>';
                    }
                    self::addButton($which, $group);
                    echo '</div>';
                }
                if ($group->shape === 'list') {
                    self::listTable($which, $group);
                } else {
                    self::recordTable($which, $group);
                }
            }
            echo '</section>';
        }
        echo '</div>';
    }

    public static function handle(PanelNode $panel, string $which): void
    {
        $path = ProjectFile::path($which);
        if ($path === null) {
            \message::error('This settings file is not here.');
            return;
        }
        $id = (string) \http::dataset('id');
        $key = (string) \http::dataset('key');
        $text = self::posted();
        $result = match ($panel->action) {
            'save' => ProjectFile::replace($path, $id, $text) !== null ? true : false,
            'saveItem' => ProjectFile::saveItem($path, $id, $key, $text),
            'renameItem' => ProjectFile::renameItem($path, $id, $key, $text),
            'addItem' => ProjectFile::addItem($path, $id),
            'removeItem' => ProjectFile::removeItem($path, $id, $key),
            'setField' => ProjectFile::setField($path, $id, $key, (string) \http::dataset('field'), $text),
            'moveBefore' => self::relocate($path, false),
            'moveAfter' => self::relocate($path, true),
            default => null,
        };
        if ($result === null || $panel->action === '') {
            return;
        }
        if ($result === false) {
            \message::error(match ($panel->action) {
                'removeItem' => 'Leave at least one entry.',
                'renameItem' => 'That name is already used.',
                default => 'That change was not saved.',
            });
            return;
        }
        \message::confirm('Saved.');
        if (in_array($panel->action, ['renameItem', 'addItem', 'removeItem', 'setField', 'moveBefore', 'moveAfter'], true)) {
            $panel->updateView();
        }
    }

    private static function relocate(string $path, bool $after): ?bool
    {
        $id = (string) \http::dataset('id');
        $fromId = (string) \http::dragdata('id');
        $fromFile = (string) \http::dragdata('file');
        $file = (string) \http::dataset('file');
        if ($fromId === '' || $fromId !== $id || ($fromFile !== '' && $file !== '' && $fromFile !== $file)) {
            return false;
        }
        return ProjectFile::moveItem(
            $path,
            $id,
            (string) \http::dragdata('key'),
            (string) \http::dataset('key'),
            $after,
        );
    }

    /** @param list<Group> $scalars */
    private static function scalarTable(string $which, array $scalars, ?Group $languages = null): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<div class="tableScroll"><table class="scalars"><thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
        foreach ($scalars as $group) {
            echo '<tr><td><span>' . $e(self::human($group->label)) . '<small>' . $e(ltrim($group->label, '$')) . '</small></span></td><td>';
            if ($group->id === 'define:DEFAULT_LANG' && $languages !== null) {
                self::languageSelect($which, $group, $languages);
            } else {
                $leaf = new Leaf($group->id, $group->category, $group->label, $group->kind, $group->text, $group->start, $group->end);
                self::control($which, $leaf, 'save');
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function languageSelect(string $which, Group $setting, Group $languages): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $choices = [];
        foreach ($languages->items as $item) {
            $label = $item->key;
            foreach ($item->fields as $field) {
                if (ProjectFile::fieldName($languages, $item, $field) === 'label' && $field->text !== '') {
                    $label = $field->text;
                }
            }
            $choices[$item->key] = $label;
        }
        if ($setting->text !== '' && !isset($choices[$setting->text])) {
            $choices = [$setting->text => $setting->text] + $choices;
        }
        echo '<select data-on_change="save" data-file="' . $e($which) . '" data-id="' . $e($setting->id) . '" name="value">';
        foreach ($choices as $key => $label) {
            $selected = (string) $key === $setting->text ? ' selected' : '';
            echo '<option value="' . $e($key) . '"' . $selected . '>' . $e($label) . '</option>';
        }
        echo '</select>';
    }

    private static function recordTable(string $which, Group $group): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $columns = ProjectFile::columns($group);
        echo '<div class="tableScroll"><table class="projectRows"><thead><tr><th></th><th>Key</th>';
        foreach ($columns as $column) {
            echo '<th>' . $e(self::human($column)) . '</th>';
        }
        echo '<th></th></tr></thead><tbody>';
        if ($group->items === []) {
            echo '<tr><td colspan="' . (count($columns) + 3) . '">Nothing here yet.</td></tr>';
        }
        foreach ($group->items as $item) {
            $data = self::data($which, $group->id, $item->key);
            echo '<tr><td class="pageSettingDrag">';
            self::grip($data);
            echo '</td><td><input data-on_blur="renameItem"' . $data . ' name="value" value="' . $e($item->key) . '" autocomplete="off"></td>';
            foreach ($columns as $column) {
                echo '<td>';
                $leaf = null;
                foreach ($item->fields as $field) {
                    if (ProjectFile::fieldName($group, $item, $field) === $column) {
                        $leaf = $field;
                        break;
                    }
                }
                if ($leaf !== null) {
                    self::control($which, $leaf, 'save');
                } else {
                    $kind = self::columnKind($group, $column);
                    $blank = new Leaf($group->id, '', $column, $kind, '', 0, 0);
                    if (self::isFlag($blank)) {
                        echo '<label class="projectSwitch"><input type="checkbox" class="switch" data-on_change="setField"' . $data . ' data-field="' . $e($column) . '" name="value"><div><div></div></div></label>';
                    } else {
                        echo '<input data-on_blur="setField"' . $data . ' data-field="' . $e($column) . '" name="value" value="" autocomplete="off">';
                    }
                }
                echo '</td>';
            }
            echo '<td class="projectRemove">';
            self::removeButton($data);
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function listTable(string $which, Group $group): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<div class="tableScroll"><table class="projectRows"><thead><tr><th></th><th>Value</th><th></th></tr></thead><tbody>';
        if ($group->items === []) {
            echo '<tr><td colspan="3">Nothing here yet.</td></tr>';
        }
        foreach ($group->items as $item) {
            $data = self::data($which, $group->id, $item->key);
            echo '<tr><td class="pageSettingDrag">';
            self::grip($data);
            echo '</td><td><input data-on_blur="saveItem"' . $data . ' name="value" value="' . $e($item->text) . '" autocomplete="off"></td><td class="projectRemove">';
            self::removeButton($data);
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function control(string $which, Leaf $leaf, string $action): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $attrs = ' data-file="' . $e($which) . '" data-id="' . $e($leaf->id) . '" name="value" autocomplete="off"';
        if (self::isFlag($leaf)) {
            $checked = self::isOn($leaf) ? ' checked' : '';
            echo '<label class="projectSwitch"><input type="checkbox" class="switch" data-on_change="' . $e($action) . '"' . $attrs . $checked . '><div><div></div></div></label>';
            return;
        }
        if ($leaf->kind === 'string' && strlen($leaf->text) > 80) {
            echo '<textarea data-on_blur="' . $e($action) . '"' . $attrs . ' rows="3">' . $e($leaf->text) . '</textarea>';
            return;
        }
        echo '<input data-on_blur="' . $e($action) . '"' . $attrs . ' value="' . $e($leaf->text) . '">';
    }

    private static function addButton(string $which, Group $group): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<button type="button" class="button" data-on_click="addItem" data-file="' . $e($which) . '" data-id="' . $e($group->id) . '"><i class="fa-regular fa-plus"></i>Add</button>';
    }

    private static function removeButton(string $data): void
    {
        echo '<button type="button" class="button transparent" title="Remove" data-on_click="userConfirm" data-action="removeItem" data-confirm="Remove this entry?"' . $data . '><i class="fa-regular fa-trash"></i></button>';
    }

    private static function grip(string $data): void
    {
        echo '<i class="fa-regular fa-grip-vertical" title="Drag to reorder" data-drag_type="setting"' . $data . '></i>';
        echo '<div class="sort v insertBefore" data-drop_accept="setting" data-on_drop="moveBefore"' . $data . '></div>';
        echo '<div class="sort v insertAfter" data-drop_accept="setting" data-on_drop="moveAfter"' . $data . '></div>';
    }

    private static function data(string $which, string $id, string $key): string
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        return ' data-file="' . $e($which) . '" data-id="' . $e($id) . '" data-key="' . $e($key) . '"';
    }

    private static function columnKind(Group $group, string $column): string
    {
        foreach ($group->items as $item) {
            foreach ($item->fields as $leaf) {
                if (ProjectFile::fieldName($group, $item, $leaf) === $column) {
                    return $leaf->kind;
                }
            }
        }
        return 'string';
    }

    private static function isFlag(Leaf $leaf): bool
    {
        return $leaf->kind === 'bool' || ($leaf->kind === 'number' && preg_match('/^[01]$/', $leaf->text) === 1);
    }

    private static function isOn(Leaf $leaf): bool
    {
        return $leaf->kind === 'bool' ? $leaf->text === 'true' : $leaf->text === '1';
    }

    private static function posted(): string
    {
        $values = \http::values();
        if (is_object($values)) {
            return (string) ($values->value ?? '');
        }
        if (is_array($values)) {
            return (string) ($values['value'] ?? '');
        }
        return (string) $values;
    }

    public static function human(string $label): string
    {
        $label = ltrim($label, '$');
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label) ?? $label;
        $label = str_replace(['_', '::', '/', '-'], ' ', $label);
        $label = trim((string) preg_replace('/\s+/', ' ', $label));
        return ucfirst(strtolower($label));
    }
}
