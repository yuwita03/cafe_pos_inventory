<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', ['status' => $request->session()->get('status')]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $request->user()->getAuthPassword());

        $intended = $request->session()->pull('url.intended');
        if (is_string($intended) && $this->canVisit($request, $intended)) {
            return redirect()->to($intended);
        }

        return to_route($request->user()->landingRoute());
    }

    private function canVisit(Request $request, string $url): bool
    {
        $destination = Request::create($url);
        if ($destination->getSchemeAndHttpHost() !== $request->getSchemeAndHttpHost() || ! $destination->is('admin/*')) {
            return false;
        }
        try {
            $route = Route::getRoutes()->match($destination);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }
        foreach ($route->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'can:') && $request->user()->cannot(substr($middleware, 4))) {
                return false;
            }
        }

        return true;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
