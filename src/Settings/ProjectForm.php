<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Panels\PanelNode;

/**
 * Global Settings and Media Settings: one table per group in the file.
 */
final class ProjectForm
{
    /** Record fields that name page templates (`a;b`, empty for all) - ticked, not typed. */
    private const TEMPLATE_FIELDS = ['var:media_groups' => 'templates'];

    public static function render(string $which): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $title = $which === 'media' ? 'Media Settings' : 'Global Settings';
        $path = ProjectFile::path($which);
        $filename = $path !== null ? basename($path) : (ProjectFile::FILES[$which] ?? '');
        echo '<div class="projectFiles">';
        echo '<h1>' . $e($title) . '</h1>';
        echo '<p class="tagsHint">' . ($which === 'media' ? 'Image and video formats control generated media. Groups define where files can be used and which uploads are allowed.' : 'Shared defaults for this project. Page-specific settings can provide their own values.') . '</p>';
        echo \message::flush();
        if ($path === null) {
            echo '<p>Missing ' . $e($filename) . '.</p>';
            if ($which === 'page') {
                self::systemSettings();
            }
            echo '</div>';
            return;
        }
        $sections = [];
        $legalSettings = [];
        $hasLanguages = false;
        $autoLanguage = null;
        foreach (ProjectFile::groups($path) as $group) {
            if (in_array(substr($group->id, 7), LegalLinks::FIELDS, true) && str_starts_with($group->id, 'define:')) {
                $legalSettings[] = $group;
                continue;
            }
            if ($group->id === 'define:LANGUAGES') {
                $hasLanguages = true;
            }
            if ($group->id === 'define:AUTO_LANGUAGE_DETECTION') {
                $autoLanguage = $group;
            }
            $name = $group->category !== '' ? $group->category : $title;
            $sections[$name][] = $group;
        }
        foreach ($sections as $name => $groups) {
            $scalars = array_values(array_filter($groups, static fn(Group $group): bool => $group->shape === 'scalar'));
            $collections = array_values(array_filter($groups, static fn(Group $group): bool => $group->shape !== 'scalar'));
            $only = $scalars === [] && count($collections) === 1;
            $headerButton = null;
            if ($only) {
                $headerButton = $collections[0];
            } elseif ($collections !== []) {
                $firstHeading = self::human($collections[0]->label);
                if (strcasecmp($firstHeading, (string) $name) === 0) {
                    $headerButton = $collections[0];
                }
            }
            $languageList = null;
            foreach ($collections as $group) {
                if ($group->id === 'define:LANGUAGES') {
                    $languageList = $group;
                }
            }
            $defaultLang = null;
            $plainScalars = [];
            foreach ($scalars as $group) {
                if ($group->id === 'define:DEFAULT_LANG' && $languageList !== null) {
                    $defaultLang = $group;
                    continue;
                }
                if ($group->id === 'define:AUTO_LANGUAGE_DETECTION' && $hasLanguages) {
                    continue;
                }
                $plainScalars[] = $group;
            }
            if ($plainScalars === [] && $collections === []) {
                continue;
            }
            echo '<section class="widget">';
            echo '<header><h2>' . $e($name) . '</h2>';
            if ($which === 'media' && in_array($name, ['Image formats','Media groups'], true)) {
                $help = $name === 'Media groups' ? 'Groups restrict uploads, replacements and moved files. File types are chosen in a dialog. This does not make files private.' : 'Choose either a megapixel budget or pixel dimensions. Megapixels may have a fixed aspect ratio (e.g. 16:9); leave it empty to crop freely. One pixel dimension may stay empty to keep the proportions. Show in editor controls the format selector, not public access.';
                echo '<button type="button" class="button transparent icononly settingInfo" data-setting-help="' . $e($help) . '" title="' . $e($help) . '" aria-label="About ' . $e($name) . '"><i class="cmsIcon cmsIcon-circle-info"></i></button>';
            }
            if ($headerButton !== null) {
                self::addButton($which, $headerButton);
            }
            echo '</header>';
            if ($plainScalars !== []) {
                self::scalarTable($which, $plainScalars, null);
            }
            foreach ($collections as $group) {
                if ($group->id === 'define:LANGUAGES') {
                    self::languageSection($which, $group, $defaultLang, $hasLanguages ? $autoLanguage : null);
                    continue;
                }
                if (!$only && $group !== $headerButton) {
                    $heading = self::human($group->label);
                    $repeat = strcasecmp($heading, (string) $name) === 0;
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
        if ($which === 'page') {
            self::systemSettings($legalSettings);
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
            'setImageSizing' => ProjectJson::setImageSizing($path, $key, $text),
            'setImageVisibility' => ProjectFile::setField($path, 'call:mediaFormats::add', $key, 'cms_display', in_array($text, ['1','true','on'], true) ? 'image' : 'none'),
            'setField' => ProjectFile::setField($path, $id, $key, (string) \http::dataset('field'), $text),
            'toggleTemplate' => self::toggleTemplate($path, $id, $key),
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
                'renameItem' => 'Use a unique key with letters, digits, underscores or hyphens and no spaces.',
                default => 'That change was not saved.',
            });
            return;
        }
        // Generated files only; retain original files, saved crops and adjustments.
        if ($which === 'media' && ($panel->action === 'setImageSizing' || ($panel->action === 'setField' && $id === 'call:mediaFormats::add' && in_array((string) \http::dataset('field'), ['aspect_ratio','megapixels','width','height'], true)))) {
            \Systopic\System\Tables\Medias\Files::clearFormat($key);
        }
        \message::confirm('Saved.');
        // Reordering moves the row in the browser. A redraw would drop the
        // scroll position and whatever had focus.
        if (in_array($panel->action, ['renameItem', 'addItem', 'removeItem', 'setField', 'setImageSizing'], true)) {
            $panel->updateView();
        }
    }

    private static function toggleTemplate(string $path, string $id, string $key): ?bool
    {
        $field = self::TEMPLATE_FIELDS[$id] ?? '';
        $template = trim((string) \http::dataset('template'));
        if ($field === '' || $template === '' || str_contains($template, ';')) {
            return false;
        }
        $current = self::templatesOf($path, $id, $key, $field);
        $current = array_values(array_diff($current, [$template]));
        if (in_array(self::posted(), ['1', 'true', 'on'], true)) {
            $current[] = $template;
        }
        return ProjectFile::setField($path, $id, $key, $field, implode(';', $current)) ?? true;
    }

    /** @return list<string> */
    private static function templatesOf(string $path, string $id, string $key, string $field): array
    {
        foreach (ProjectFile::groups($path) as $group) {
            foreach ($group->id === $id ? $group->items : [] as $item) {
                foreach ($item->key === $key ? $item->fields : [] as $leaf) {
                    if (ProjectFile::fieldName($group, $item, $leaf) === $field) {
                        return array_values(array_filter(array_map('trim', explode(';', $leaf->text))));
                    }
                }
            }
        }
        return [];
    }

    /** @param list<string> $picked */
    private static function templatePicker(string $data, array $picked): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $templates = class_exists('template') ? (array) \template::getAll() : [];
        uksort($templates, 'strnatcmp');
        $some = $picked !== [] ? ' data-some' : '';
        echo '<details class="projectTemplates"' . $some . '><summary title="Choose templates"><span class="all">All templates</span><span class="some">Only the ticked</span><i class="cmsIcon cmsIcon-pen"></i></summary><div class="projectTemplatesMenu">';
        foreach ($templates as $name => $template) {
            $checked = in_array((string) $name, $picked, true) ? ' checked' : '';
            $shown = trim(str_replace(['_', '-'], ' ', (string) ($template->name ?? $name)));
            echo '<label class="projectSwitch"><input type="checkbox" class="switch" data-on_change="toggleTemplate"' . $data . ' data-template="' . $e($name) . '" name="value"' . $checked . '><div><div></div></div><span>' . $e($shown) . '</span></label>';
        }
        echo '<small>No tick: on every template.</small></div></details>';
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
    private static function scalarTable(string $which, array $scalars, ?Group $languages = null, bool $frame = true): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        if ($frame) echo '<div class="tableScroll"><table class="scalars"><thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
        foreach ($scalars as $group) {
            $legal = in_array($group->label, LegalLinks::FIELDS, true);
            $label = $legal ? match ($group->label) { 'CMS_IMPRINT_URL' => 'System imprint URL', 'CMS_PRIVACY_URL' => 'System privacy URL', 'CMS_OPERATOR_DETAILS' => 'Responsible operator', default => 'Data protection officer (if appointed)' } : self::human($group->label);
            echo '<tr><td><span>' . $e($label);
            if (!$legal) echo '<small>' . $e(ltrim($group->label, '$')) . '</small>';
            if ($group->label === 'CMS_OPERATOR_DETAILS') echo '<small class="settingNote">Name or company, full postal address and contact email of the responsible operator. Shown publicly in System Privacy. These details may instead be supplied in the linked imprint and privacy policy.</small>';
            elseif ($group->label === 'CMS_DPO_DETAILS') echo '<small class="settingNote">Contact details of the appointed data protection officer. Leave empty if none is appointed. Shown publicly in System Privacy; never enter passwords or SMTP credentials.</small>';
            elseif ($legal) echo '<small class="settingNote">Optional HTTP(S) URL or path starting with /. Leave empty to use the frontend ' . ($group->label === 'CMS_IMPRINT_URL' ? '/imprint/' : '/privacy/') . ' page.</small>';
            echo '</span></td><td>';
            if ($group->id === 'define:DEFAULT_LANG' && $languages !== null) {
                self::languageSelect($which, $group, $languages);
            } else {
                $leaf = new Leaf($group->id, $group->category, $group->label, $group->kind, $group->text, $group->start, $group->end);
                self::control($which, $leaf, 'save', $label);
            }
            echo '</td></tr>';
        }
        if ($frame) echo '</tbody></table></div>';
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
        echo '<select data-on_change="save" data-file="' . $e($which) . '" data-id="' . $e($setting->id) . '" name="value" aria-label="Default language">';
        foreach ($choices as $key => $label) {
            $selected = (string) $key === $setting->text ? ' selected' : '';
            echo '<option value="' . $e($key) . '"' . $selected . '>' . $e($label) . '</option>';
        }
        echo '</select>';
    }

    private static function languageSection(string $which, Group $group, ?Group $defaultLang, ?Group $autoLanguage): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<div class="tableScroll">';
        if ($defaultLang !== null) {
            echo '<div class="langBar"><span>Default</span>';
            self::languageSelect($which, $defaultLang, $group);
            echo '</div>';
        }
        if ($autoLanguage !== null) {
            $leaf = new Leaf($autoLanguage->id, $autoLanguage->category, $autoLanguage->label, $autoLanguage->kind, $autoLanguage->text, $autoLanguage->start, $autoLanguage->end);
            echo '<div class="langDetect"><span>' . $e(self::human($autoLanguage->label)) . '<small>' . $e(ltrim($autoLanguage->label, '$')) . '</small><small class="langNote">Applies to the public website only, not to this system.</small></span>';
            self::control($which, $leaf, 'save');
            echo '</div>';
        }
        echo '<div class="languageFlagHelp"><p class="tagsHint">Custom flags apply only to this project. SVG only, up to 128 KB.</p><a class="button cmsExternalLink" href="' . $e(LanguageFlags::SOURCE) . '" target="_blank" rel="noopener noreferrer">Download SVG flags<i class="cmsIcon cmsIcon-arrow-up-right-from-square" aria-hidden="true"></i></a></div>';
        self::recordTable($which, $group, false);
        echo '</div>';
    }

    /** @param list<Group> $legalSettings Project-wide legal links beside the instance sign-in default. */
    private static function systemSettings(array $legalSettings = []): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $file = EnvironmentFile::activeFile();
        if ($file === null && $legalSettings === []) return;
        $data = $file !== null ? EnvironmentFile::read($file) : [];
        $auth = is_array($data['auth'] ?? null) ? $data['auth'] : [];
        $method = (string) ($auth['default_login_method'] ?? '');
        if (!in_array($method, ['', 'code', 'device', 'passkey', 'password'], true)) {
            $method = '';
        }
        echo '<section class="widget"><header><h2>System</h2></header>';
        echo '<div class="tableScroll">';
        echo '<table class="scalars"><thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
        if ($file !== null) {
            echo '<tr class="securityRow"><td><span>Default sign-in<small>default_login_method</small><small class="settingNote">Which sign-in opens first. Ask each time shows the method list.</small></span></td><td>';
            echo '<select data-on_change="saveAuthMethod" data-file="' . $e($file) . '" data-group="auth" data-key="default_login_method" name="value" aria-label="Default sign-in">';
            foreach (['' => 'Ask each time', 'code' => 'Sign-in code', 'device' => 'Remote login', 'passkey' => 'Passkey', 'password' => 'Username & password'] as $option => $label) {
                echo '<option value="' . $e($option) . '"' . ($method === $option ? ' selected' : '') . '>' . $e($label) . '</option>';
            }
            echo '</select></td></tr>';
        }
        self::scalarTable('page', $legalSettings, null, false);
        echo '</tbody></table></div></section>';
    }

    private static function recordTable(string $which, Group $group, bool $frame = true): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $columns = ProjectFile::columns($group);
        $images = $group->id === 'call:mediaFormats::add';
        $mediaGroups = $group->id === 'var:media_groups';
        if ($group->id === 'define:VIDEO_FORMATS') $columns = ['label', ...array_diff($columns, ['label'])];
        if ($images) $columns = ['label', 'size', ...array_diff($columns, ['label','width','height','megapixels','aspect_ratio','autocrop','autocrop_limit','cms_display']), 'show_in_editor'];
        if ($mediaGroups) $columns = array_values(array_unique([...array_diff($columns,['public']), 'file_extension', 'templates']));
        $templateField = self::TEMPLATE_FIELDS[$group->id] ?? null;
        if ($templateField !== null) {
            $columns = [...array_diff($columns, [$templateField]), $templateField];
        }
        $languages = $group->id === 'define:LANGUAGES';
        if ($languages) $columns = array_values(array_diff($columns, ['code']));
        if ($frame) {
            echo '<div class="tableScroll">';
        }
        echo '<table class="projectRows' . ($languages ? ' languages' : '') . '"><thead><tr><th></th>';
        if ($languages) echo '<th>Flag</th>';
        echo '<th>' . ($languages ? 'Language' : 'Key') . '</th>';
        foreach ($columns as $column) {
            $heading = $languages ? match ($column) { 'activated' => 'On', 'label' => 'Name', default => self::human($column) } : self::human($column);
            echo '<th' . ($languages && $column === 'activated' ? ' class="langOn"' : '') . '>' . $e($heading) . '</th>';
        }
        echo '<th></th></tr></thead><tbody>';
        if ($group->items === []) {
            echo '<tr><td colspan="' . (count($columns) + ($languages ? 4 : 3)) . '">Nothing here yet.</td></tr>';
        }
        foreach ($group->items as $item) {
            $data = self::data($which, $group->id, $item->key);
            echo '<tr' . ($images ? ' class="imageFormatRow"' : '') . '><td class="pageSettingDrag">';
            self::grip($data);
            echo '</td>';
            if ($languages) {
                $values = self::valuesOf($group, $item);
                echo '<td class="languageFlagCell">';
                LanguageFlags::preview(trim((string) ($values['code'] ?? '')) ?: (string) $item->key, (string) ($values['label'] ?? $item->key), (string) $item->key);
                echo '</td>';
            }
            echo '<td>';
            if ($images) echo '<label class="formatField"><span aria-hidden="true">&nbsp;</span>';
            echo '<input aria-label="' . $e(($languages ? 'Language' : 'Key') . ': ' . $item->key) . '" data-key-impact="Renaming a technical key can break template code, stored references and integrations. References are not updated automatically. Rename this key?" data-setting-key pattern="[A-Za-z0-9_][A-Za-z0-9_\\-]*" required title="Use letters, digits, underscores or hyphens; no spaces." data-on_blur="renameItem"' . $data . ' name="value" value="' . $e($item->key) . '" autocomplete="off">';
            if ($images) echo '</label>';
            echo '</td>';
            foreach ($columns as $column) {
                $on = $languages && $column === 'activated';
                echo '<td' . ($on ? ' class="langOn"' : '') . '>';
                if ($images && $column === 'size') {
                    self::imageSize($which, $group, $item, $data);
                    echo '</td>'; continue;
                }
                if ($images && $column === 'show_in_editor') {
                    $values = self::valuesOf($group,$item);
                    echo '<div class="formatField"><span aria-hidden="true">&nbsp;</span><label class="projectSwitch"><input type="checkbox" class="switch" aria-label="Show in editor" data-on_change="setImageVisibility"' . $data . ' name="value"' . (($values['cms_display'] ?? '') === 'none' ? '' : ' checked') . '><div><div></div></div></label></div></td>';
                    continue;
                }
                if ($mediaGroups && $column === 'file_extension') {
                    $values = self::valuesOf($group,$item);
                    $picked = \Systopic\System\Media\GroupPolicy::parse((string) ($values['file_extension'] ?? ''));
                    $rawTypes = trim((string) ($values['file_extension'] ?? ''));
                    $label = $rawTypes === '' || $rawTypes === '*' ? 'All supported types' : ($picked === [] ? 'No supported types' : count($picked) . (count($picked) === 1 ? ' file type' : ' file types'));
                    echo '<a class="ajax button transparent" href="' . $e(rtrim((string) \http::$root, '/') . '/cms/settings/mediasettings/types/') . '?group=' . rawurlencode($item->key) . '">' . $e($label) . '<i class="cmsIcon cmsIcon-pen"></i></a></td>';
                    continue;
                }
                if ($mediaGroups && $column === $templateField) {
                    $values = self::valuesOf($group, $item);
                    $picked = array_filter(array_map('trim', explode(';', (string) ($values['templates'] ?? ''))));
                    $label = $picked === [] ? 'All templates' : count($picked) . (count($picked) === 1 ? ' template' : ' templates');
                    echo '<a class="ajax button transparent" href="' . $e(rtrim((string) \http::$root, '/') . '/cms/settings/mediasettings/types/') . '?kind=templates&amp;group=' . rawurlencode($item->key) . '">' . $e($label) . '<i class="cmsIcon cmsIcon-pen"></i></a></td>';
                    continue;
                }
                if ($column === $templateField) {
                    $picked = [];
                    foreach ($item->fields as $field) {
                        if (ProjectFile::fieldName($group, $item, $field) === $column) {
                            $picked = array_values(array_filter(array_map('trim', explode(';', $field->text))));
                        }
                    }
                    self::templatePicker($data, $picked);
                    echo '</td>';
                    continue;
                }
                $leaf = null;
                foreach ($item->fields as $field) {
                    if (ProjectFile::fieldName($group, $item, $field) === $column) {
                        $leaf = $field;
                        break;
                    }
                }
                if ($images && $column === 'label') echo '<label class="formatField"><span aria-hidden="true">&nbsp;</span>';
                if ($leaf !== null) {
                    self::control($which, $leaf, 'save', ($languages && $column === 'label' ? 'Name' : ($on ? 'On' : self::human($column))) . ': ' . $item->key);
                } else {
                    $kind = self::columnKind($group, $column);
                    $blank = new Leaf($group->id, '', $column, $kind, '', 0, 0);
                    $fieldData = $data . ' aria-label="' . $e(self::human($column) . ': ' . $item->key) . '"';
                    if (self::isFlag($blank)) {
                        echo '<label class="projectSwitch"><input type="checkbox" class="switch" data-on_change="setField"' . $fieldData . ' data-field="' . $e($column) . '" name="value"><div><div></div></div></label>';
                    } else {
                        echo '<input data-on_blur="setField"' . $fieldData . ' data-field="' . $e($column) . '" name="value" value="" autocomplete="off">';
                    }
                }
                if ($images && $column === 'label') echo '</label>';
                echo '</td>';
            }
            echo '<td class="projectRemove' . ($languages ? ' languageActions' : '') . '">';
            if ($languages) {
                echo '<div class="languageRowActions">';
                $values = self::valuesOf($group, $item);
                LanguageFlags::control(trim((string) ($values['code'] ?? '')) ?: (string) $item->key, (string) ($values['label'] ?? $item->key), (string) $item->key);
            }
            self::removeButton($data, (string) $item->key);
            if ($languages) echo '</div>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        if ($frame) {
            echo '</div>';
        }
    }

    private static function valuesOf(Group $group, Item $item): array
    {
        $values=[];
        foreach ($item->fields as $leaf) $values[ProjectFile::fieldName($group,$item,$leaf)]=$leaf->text;
        return $values;
    }

    private static function imageSize(string $which, Group $group, Item $item, string $data): void
    {
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES);
        $values=self::valuesOf($group,$item);
        $mp=(float)($values['megapixels']??0)>0;
        echo '<div class="formatSizing"><label><span aria-hidden="true">&nbsp;</span><select name="value" data-on_change="setImageSizing"' . $data . ' aria-label="Sizing mode"><option value="megapixels"' . ($mp?' selected':'') . '>Megapixels</option><option value="pixels"' . (!$mp?' selected':'') . '>Pixel dimensions</option></select></label>';
        foreach ($mp ? ['megapixels'=>'MP'] : ['width'=>'Width','height'=>'Height'] as $field=>$label) {
            echo '<label><span>' . $label . '</span><input type="number" min="' . ($mp?'0.01':'0') . '" step="' . ($mp?'any':'1') . '" name="value" data-on_blur="setField"' . $data . ' data-field="' . $field . '" value="' . $e($values[$field]??'') . '" aria-label="' . $label . '"></label>';
        }
        if ($mp) echo '<label><span>Aspect ratio</span><input type="text" name="value" data-on_blur="setField"' . $data . ' data-field="aspect_ratio" value="' . $e($values['aspect_ratio']??'') . '" placeholder="Free · e.g. 16:9" pattern="[0-9]+([.][0-9]+)?((:|/)[0-9]+([.][0-9]+)?)?" title="Positive width:height, e.g. 16:9. Leave empty for a free crop." aria-label="Aspect ratio"></label>';
        echo '</div>';
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
            echo '</td><td><input aria-label="' . $e(self::human($group->label) . ': ' . $item->key) . '" data-on_blur="saveItem"' . $data . ' name="value" value="' . $e($item->text) . '" autocomplete="off"></td><td class="projectRemove">';
            self::removeButton($data, (string) $item->key);
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function control(string $which, Leaf $leaf, string $action, ?string $label = null): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $attrs = ' aria-label="' . $e($label ?? self::human($leaf->label)) . '" data-file="' . $e($which) . '" data-id="' . $e($leaf->id) . '" name="value" autocomplete="off"';
        if (in_array(substr($leaf->id, 7), LegalLinks::DETAILS, true) && str_starts_with($leaf->id, 'define:')) {
            echo '<textarea rows="3" maxlength="5000" data-on_blur="' . $e($action) . '"' . $attrs . '>' . $e($leaf->text) . '</textarea>';
            return;
        }
        if (in_array($leaf->id, ['define:CMS_IMPRINT_URL', 'define:CMS_PRIVACY_URL'], true)) {
            $pattern = 'https?://[^\\x2f\\s\\\\@?#]+([\\x2f?#][^\\s\\\\]*)?|/(?!/)[^\\s\\\\]*';
            echo '<input class="systemLegalUrl" type="text" inputmode="url" spellcheck="false" pattern="' . $e($pattern) . '" title="Use an HTTP(S) URL, a path starting with /, or leave empty." data-on_blur="' . $e($action) . '"' . $attrs . ' value="' . $e($leaf->text) . '">';
            return;
        }
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
        echo '<button type="button" class="button" data-on_click="addItem" data-file="' . $e($which) . '" data-id="' . $e($group->id) . '"><i class="cmsIcon cmsIcon-plus"></i>Add</button>';
    }

    private static function removeButton(string $data, string $context): void
    {
        echo '<button type="button" class="button transparent" title="Remove" aria-label="Remove: ' . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . '" data-on_click="userConfirm" data-action="removeItem" data-confirm="Remove this entry?"' . $data . '><i class="cmsIcon cmsIcon-trash"></i></button>';
    }

    private static function grip(string $data): void
    {
        echo '<i class="cmsIcon cmsIcon-grip-dots-vertical" aria-label="Drag to reorder" data-drag_type="setting"' . $data . '></i>';
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

    public static function searchHits(\Systopic\System\Sys\Search $search, string $which, string $href): void
    {
        $path = ProjectFile::path($which);
        $title = $which === 'media' ? 'Media Settings' : 'Global Settings';
        foreach ($path === null ? [] : ProjectFile::groups($path) as $group) {
            $label = self::human($group->label);
            if ($search->matches($label) || $search->matches(ltrim($group->label, '$')) || $search->matches($group->category)) {
                $search->add('Settings', 'cmsIcon-gear', $label, $group->category !== '' ? $title . ' › ' . $group->category : $title, $href);
            }
        }
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
