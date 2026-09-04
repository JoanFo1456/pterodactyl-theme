<?php

namespace JoanFo\PterodactylUi\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JoanFo\PterodactylUi\Support\BootstrapPayload;
use JoanFo\PterodactylUi\Support\PanelBridge;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Describes the parts of the panel the React app can't know statically — chiefly the pages
 * other plugins have registered, which change whenever a plugin is enabled or disabled.
 */
class ManifestController extends Controller
{
    public function __construct(private BootstrapPayload $payload, private PanelBridge $bridge) {}

    public function bootstrap(Request $request): JsonResponse
    {
        return new JsonResponse($this->payload->build($request->user()));
    }

    /**
     * Plugin-contributed pages for one server, resolved with that server as the tenant so
     * per-server authorization and URL generation behave exactly as they do in Filament.
     */
    public function serverNavigation(Request $request, string $server): JsonResponse
    {
        $model = Server::query()
            ->where('uuid_short', $server)
            ->orWhere('uuid', $server)
            ->first();

        throw_unless($model instanceof Server, new NotFoundHttpException(trans('exceptions.api.resource_not_found')));

        $user = $request->user();

        throw_unless($user !== null && $user->canAccessTenant($model), new NotFoundHttpException(trans('exceptions.api.resource_not_found')));

        return new JsonResponse([
            'server' => [
                'uuid' => $model->uuid,
                'identifier' => $model->uuid_short,
            ],
            'pages' => $this->bridge->serverPages($model),
        ]);
    }
}
