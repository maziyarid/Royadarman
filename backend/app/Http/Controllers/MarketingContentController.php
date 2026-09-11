<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\UserRole;
use App\Models\MarketingPage;
use App\Models\MarketingPageRevision;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MarketingContentController extends Controller
{
    private const ALLOWED_SLUGS = ['home', 'services/opg', 'services/home-dentistry', 'referrals', 'contact'];

    public function index(Request $request, string $locale): View
    {
        $this->authorizeOwner($request);

        return view('panel.marketing.index', [
            'pages' => MarketingPage::query()->orderBy('slug')->orderBy('locale')->get(),
            'allowedSlugs' => self::ALLOWED_SLUGS,
            'locale' => $locale,
        ]);
    }

    public function create(Request $request, string $locale): View
    {
        $this->authorizeOwner($request);

        return view('panel.marketing.edit', [
            'page' => new MarketingPage(['locale' => $locale, 'status' => 'draft', 'version' => 0]),
            'allowedSlugs' => self::ALLOWED_SLUGS,
            'locale' => $locale,
            'creating' => true,
        ]);
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $this->validated($request, null);
        $userId = (int) $request->user()->id;

        $page = DB::transaction(function () use ($data, $userId): MarketingPage {
            $page = MarketingPage::query()->create([
                ...$data,
                'status' => 'draft',
                'version' => 1,
                'created_by_user_id' => $userId,
                'updated_by_user_id' => $userId,
            ]);
            $this->recordRevision($page, $userId);

            return $page;
        });

        return redirect()->route('marketing.edit', ['locale' => $locale, 'page' => $page->id])
            ->with('status', 'saved');
    }

    public function edit(Request $request, string $locale, MarketingPage $page): View
    {
        $this->authorizeOwner($request);

        return view('panel.marketing.edit', [
            'page' => $page,
            'allowedSlugs' => self::ALLOWED_SLUGS,
            'locale' => $locale,
            'creating' => false,
        ]);
    }

    public function update(Request $request, string $locale, MarketingPage $page): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $this->validated($request, $page);
        $expectedVersion = (int) $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($page, $data, $expectedVersion, $userId): void {
            $locked = MarketingPage::query()->lockForUpdate()->findOrFail($page->id);
            if ($locked->version !== $expectedVersion) {
                throw new ConflictHttpException('Marketing content changed in another session. Reload before saving.');
            }
            $locked->fill($data);
            $locked->updated_by_user_id = $userId;
            $locked->version++;
            if ($locked->status === 'published') {
                // Editing published content returns it to draft so unreviewed edits
                // cannot silently change the public page.
                $locked->status = 'draft';
                $locked->published_at = null;
            }
            $locked->save();
            $this->recordRevision($locked, $userId);
        });

        return redirect()->route('marketing.edit', ['locale' => $locale, 'page' => $page->id])
            ->with('status', 'saved');
    }

    public function publish(Request $request, string $locale, MarketingPage $page): RedirectResponse
    {
        $this->authorizeOwner($request);
        $expectedVersion = (int) $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($page, $expectedVersion, $userId): void {
            $locked = MarketingPage::query()->lockForUpdate()->findOrFail($page->id);
            if ($locked->version !== $expectedVersion) {
                throw new ConflictHttpException('Marketing content changed in another session. Reload before publishing.');
            }
            $locked->status = 'published';
            $locked->published_at = now();
            $locked->updated_by_user_id = $userId;
            $locked->version++;
            $locked->save();
            $this->recordRevision($locked, $userId);
        });

        return redirect()->route('marketing.edit', ['locale' => $locale, 'page' => $page->id])
            ->with('status', 'published');
    }

    private function validated(Request $request, ?MarketingPage $page): array
    {
        return $request->validate([
            'slug' => ['required', Rule::in(self::ALLOWED_SLUGS), Rule::unique('marketing_pages', 'slug')->where(fn ($q) => $q->where('locale', $request->input('locale')))->ignore($page?->id)],
            'locale' => ['required', 'in:fa,ar,en'],
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:50000'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ]);
    }

    private function recordRevision(MarketingPage $page, int $userId): void
    {
        MarketingPageRevision::query()->create([
            'marketing_page_id' => $page->id,
            'version' => $page->version,
            'title' => $page->title,
            'excerpt' => $page->excerpt,
            'body' => $page->body,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'status' => $page->status,
            'actor_user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->role === UserRole::Owner && $request->user()->is_active, 403);
    }
}
