<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\FaqCatalog;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqsController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $page = Page::getBySlug('faqs');

        $seo = Seo::forPath('/faqs');
        if ($page?->meta_title) {
            $seo['title'] = $page->meta_title;
        }
        if ($page?->meta_description) {
            $seo['description'] = $page->meta_description;
        }

        $items = FaqCatalog::search($query !== '' ? $query : null);
        $categories = FaqCatalog::categories();

        $grouped = [];
        foreach ($categories as $slug => $meta) {
            $catItems = array_values(array_filter(
                $items,
                fn (array $item) => ($item['category'] ?? '') === $slug
            ));
            if ($catItems !== []) {
                $grouped[$slug] = [
                    'meta' => $meta,
                    'items' => $catItems,
                ];
            }
        }

        $jsonLdExtra = [
            FaqCatalog::faqPageJsonLd($items),
        ];

        return view('faqs.index', [
            'page' => $page,
            'seo' => $seo,
            'query' => $query,
            'grouped' => $grouped,
            'categories' => $categories,
            'totalCount' => count($items),
            'jsonLdExtra' => $jsonLdExtra,
        ]);
    }
}
