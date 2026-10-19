<?php

declare(strict_types=1);

namespace Gcego\Panels\Http\Controllers;

use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\Registry\PanelMembership;
use Gcego\Panels\Routing\PanelRoutes;
use Gcego\Panels\Shells\PanelShellFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel home ("<namespace>home", the panel root): a redirect to the
 * panel's dashboard page (whose own authorization then applies), or the
 * panel's welcome state when it has none. Runs after AuthorizePanel.
 */
final readonly class PanelHomeController
{
    public function __construct(
        private PanelContextResolver $contexts,
        private PanelMembership $membership,
        private PanelRoutes $routes,
        private PanelShellFactory $shells,
    ) {}

    public function __invoke(): Response
    {
        $context = $this->contexts->require();

        if ($context->panel->dashboard !== null) {
            $url = $this->routes->url($this->routes->pageRoute($context->panel, $this->membership->pageIdentity($context->panel, $context->panel->dashboard)));

            if ($url !== null) {
                return new RedirectResponse($url);
            }
        }

        $shell = $this->shells->make($context, $context->panel->title);

        return response()->view('gcego-panels::shells.document', [
            'shell' => $shell,
            'content' => new HtmlString(view('gcego-panels::states.home', ['title' => $context->panel->title, 'description' => $context->panel->description])->render()),
        ]);
    }
}
