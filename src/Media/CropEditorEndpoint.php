<?php

declare(strict_types=1);

namespace Systopic\System\Media;

use Systopic\Db\Scope;
use Systopic\System\Queries\MediaCrops\MediaCrops;

/**
 * The server's side of the image editor (`public/js/gui/cropEditor.js`):
 * its five requests, answered the same in every application.
 *
 *   cropState                                        nothing changes
 *   saveMasterCrop  ratio ax ay scale | auto=1       + brightness contrast saturation angle flip
 *   saveRatioCrop   ratio ax ay scale            (ax, ay: the alignment, see CropBox)
 *   resetCrop       format=<ratio>|original          back to automatic
 *   savePois        pois=<json> [drop_crops=1]
 *
 * Every one answers with the medium as it is afterwards
 * (`CropEdits::state()`): the editor redraws from that, so what it shows is
 * what was stored.
 *
 * Who may edit which medium is the application's business - it checks that
 * before it asks, and hands in the medium's id and its scope:
 *
 *   if (CropEditorEndpoint::handles($action)) {
 *       CropEditorEndpoint::send(CropEditorEndpoint::answer($action, $scope, $mediaId, http::get(...)));
 *   }
 *
 * The editor is opened with the address of the panel that does this
 * (`sys.cropEditor.open({url: …, nodeId: …})`); the action is the last part
 * of the path, the rest are query parameters.
 */
final class CropEditorEndpoint
{
    public const ACTIONS = ['cropState', 'saveMasterCrop', 'saveRatioCrop', 'resetCrop', 'savePois'];

    public static function handles(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    /**
     * @param callable(string, mixed=): mixed $param a request parameter by name, with a default
     * @return array{status: int, body: ?array<string, mixed>}
     */
    public static function answer(string $action, Scope $scope, int $mediaId, callable $param): array
    {
        $set = $mediaId > 0 && self::handles($action) ? MediaCrops::one($scope, $mediaId) : null;
        if ($set === null) {
            return ['status' => 404, 'body' => null];
        }
        $edit = ['save' => [], 'remove' => [], 'clipped' => []];
        $box = CropEdits::boxFrom($param('ratio'), $param('ax'), $param('ay'), $param('scale'));
        try {
            switch ($action) {
                case 'saveMasterCrop':
                    $auto = (int) $param('auto', 0) === 1;
                    if (!$auto && $box === null) {
                        return ['status' => 422, 'body' => null];
                    }
                    $edit = CropEdits::master($set, $auto ? null : $box, [
                        'b' => $param('brightness', 100), 'c' => $param('contrast', 100), 's' => $param('saturation', 100),
                        'r' => $param('angle', 0), 'f' => (int) $param('flip', 0) === 1 ? 1 : 0,
                    ]);
                    break;
                case 'saveRatioCrop':
                    $edit = $box === null ? null : CropEdits::crop($set, $box);
                    if ($edit === null) {
                        return ['status' => 422, 'body' => null];
                    }
                    break;
                case 'resetCrop':
                    $edit = CropEdits::reset($set, (string) $param('format', ''));
                    break;
                case 'savePois':
                    $pois = json_decode((string) $param('pois', '[]'), true);
                    if (!is_array($pois)) {
                        return ['status' => 422, 'body' => null];
                    }
                    $edit = CropEdits::pois($set, $pois, (int) $param('drop_crops', 0) === 1);
                    break;
            }
        } catch (\InvalidArgumentException) {
            return ['status' => 422, 'body' => null];
        }
        $set = CropEdits::apply($scope, $set, $edit);
        return ['status' => 200, 'body' => CropEdits::state($set) + ['clipped' => $edit['clipped']]];
    }

    /**
     * Sends an answer as the whole response: JSON, or only the status.
     *
     * @param array{status: int, body: ?array<string, mixed>} $answer
     */
    public static function send(array $answer): never
    {
        p()->erase();      // what the debugger collected would land in the JSON
        http_response_code($answer['status']);
        if ($answer['body'] !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($answer['body'], JSON_THROW_ON_ERROR);
        }
        exit;
    }
}
