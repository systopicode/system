<?php

declare(strict_types=1);

namespace Systopic\System\Media;

use Systopic\Db\Scope;
use Systopic\System\Queries\MediaCrops\MediaCrops;

/**
 * The server's side of the image editor (`public/js/gui/cropEditor.js`):
 * its requests, answered the same in every application.
 *
 *   cropState                                        nothing changes
 *   saveMasterCrop  ratio ax ay scale basis | auto=1  + brightness contrast saturation angle flip
 *   saveRatioCrop   ratio ax ay scale basis       (ax, ay: the alignment, basis fill|fit - see CropBox)
 *   saveRatioCrops  crops=<json>                     [{ratio, ax, ay, scale, basis} | {ratio, auto: true}, ...]
 *   saveFormatCrops crops=<json>                     [{format, ax, ay, scale, basis} | {format, auto: true}, ...] - fixed formats
 *   resetCrop       format=<ratio>|original          back to automatic
 *   savePois        pois=<json> [drop_crops=1]
 *
 * Every one answers with the medium as it is afterwards
 * (`CropEdits::state()`): the editor redraws from that, so what it shows is
 * what was stored. Refused (404, 422): `{error: "why"}` - the editor shows it.
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
    public const ACTIONS = ['cropState', 'saveMasterCrop', 'saveRatioCrop', 'saveRatioCrops', 'saveFormatCrops', 'resetCrop', 'savePois'];

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
            return self::refused(404, 'There is no such picture.');
        }
        $edit = ['save' => [], 'remove' => [], 'clipped' => []];
        $box = CropEdits::boxFrom($param('ratio'), $param('ax'), $param('ay'), $param('scale'), $param('basis'));
        try {
            switch ($action) {
                case 'saveMasterCrop':
                    $auto = (int) $param('auto', 0) === 1;
                    if (!$auto && $box === null) {
                        return self::refused(422, self::boxProblem($param));
                    }
                    $edit = CropEdits::master($set, $auto ? null : $box, [
                        'b' => $param('brightness', 100), 'c' => $param('contrast', 100), 's' => $param('saturation', 100),
                        'r' => $param('angle', 0), 'f' => (int) $param('flip', 0) === 1 ? 1 : 0,
                    ]);
                    break;
                case 'saveRatioCrop':
                    $edit = $box === null ? null : CropEdits::crop($set, $box);
                    if ($edit === null) {
                        return self::refused(422, $box === null ? self::boxProblem($param) : 'The ratio ' . $box->ratio->name . ' is not declared.');
                    }
                    break;
                case 'saveRatioCrops':
                    $crops = json_decode((string) $param('crops', '[]'), true);
                    $edit = is_array($crops) ? CropEdits::crops($set, $crops) : null;
                    if ($edit === null) {
                        return self::refused(422, is_array($crops) ? CropEdits::cropsProblem($crops) ?? 'The crops are not taken.' : 'The crops are no list.');
                    }
                    break;
                case 'saveFormatCrops':
                    $crops = json_decode((string) $param('crops', '[]'), true);
                    $edit = is_array($crops) ? CropEdits::formats($set, $crops) : null;
                    if ($edit === null) {
                        return self::refused(422, is_array($crops) ? CropEdits::formatsProblem($crops) ?? 'The crops are not taken.' : 'The crops are no list.');
                    }
                    break;
                case 'resetCrop':
                    $edit = CropEdits::reset($set, (string) $param('format', ''));
                    break;
                case 'savePois':
                    $pois = json_decode((string) $param('pois', '[]'), true);
                    if (!is_array($pois)) {
                        return self::refused(422, 'The points of interest are no list.');
                    }
                    $edit = CropEdits::pois($set, $pois, (int) $param('drop_crops', 0) === 1);
                    break;
            }
        } catch (\InvalidArgumentException $e) {
            return self::refused(422, $e->getMessage());
        }
        $set = CropEdits::apply($scope, $set, $edit);
        return ['status' => 200, 'body' => CropEdits::state($set) + ['clipped' => $edit['clipped']]];
    }

    /**
     * Not done, and why - the editor shows the reason.
     *
     * @return array{status: int, body: array{error: string}}
     */
    private static function refused(int $status, string $reason): array
    {
        return ['status' => $status, 'body' => ['error' => $reason]];
    }

    /** Why the crop a request sends is none. */
    private static function boxProblem(callable $param): string
    {
        return CropEdits::boxProblem($param('ratio'), $param('ax'), $param('ay'), $param('scale'), $param('basis')) ?? 'That is no crop.';
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
