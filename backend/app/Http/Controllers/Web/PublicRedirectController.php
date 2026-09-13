<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cms\Redirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class PublicRedirectController extends Controller
{
    public function resolve(Request $request): SymfonyResponse
    {
        $path = '/'.ltrim($request->path(), '/');

        $redirect = Redirect::query()
            ->where('source_path', $path)
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            $redirect->increment('hit_count');

            return redirect(
                $redirect->destination_url,
                (int) $redirect->status_code,
            );
        }

        abort(404);
    }
}
