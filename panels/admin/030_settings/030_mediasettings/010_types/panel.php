<?php
declare(strict_types=1);
namespace Systopic\System\Panels\Root\Admin\Settings\Mediasettings\Types;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Settings\ProjectFile;
final class Panel extends PanelNode
{
    public bool $inNavi = false;
    public function getDefaultState() { return (object)['group'=>'','kind'=>'types']; }
    public function onLoad() {
        if (\http::get('group') !== null) {
            $this->state->group = (string) \http::get('group');
            $this->state->kind = \http::get('kind') === 'templates' ? 'templates' : 'types';
        }
    }
    public function choices(): array {
        if ($this->state->kind !== 'templates') return \Systopic\System\Media\GroupPolicy::TYPES;
        $choices = [];
        foreach (class_exists('template') ? (array) \template::getAll() : [] as $key => $template) {
            $choices[(string) $key] = [trim(str_replace(['_', '-'], ' ', (string) ($template->name ?? $key))), (string) $key];
        }
        uksort($choices, 'strnatcmp');
        return $choices;
    }
    public function groupData(): ?array {
        $path=ProjectFile::path('media');
        if (!$path) return null;
        foreach (ProjectFile::groups($path) as $group) if ($group->id==='var:media_groups') {
            foreach ($group->items as $item) if ($item->key===$this->state->group) {
                $values=[];
                foreach ($item->fields as $leaf) $values[ProjectFile::fieldName($group,$item,$leaf)]=$leaf->text;
                return $values;
            }
        }
        return null;
    }
}
