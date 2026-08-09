<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\Page;
use Illuminate\Http\Response;
use SimpleXMLElement;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('public.courses.index'), 'lastmod' => null],
            ['loc' => route('public.blog.index'), 'lastmod' => null],
            ['loc' => route('public.bloggers.index'), 'lastmod' => null],
        ];

        Course::query()
            ->published()
            ->select(['slug', 'updated_at'])
            ->latest('updated_at')
            ->get()
            ->each(function (Course $course) use (&$urls): void {
                $urls[] = [
                    'loc' => route('public.courses.show', $course->slug),
                    'lastmod' => $course->updated_at?->toAtomString(),
                ];
            });

        BlogPost::query()
            ->published()
            ->select(['slug', 'updated_at'])
            ->latest('updated_at')
            ->get()
            ->each(function (BlogPost $post) use (&$urls): void {
                $urls[] = [
                    'loc' => route('public.blog.show', $post->slug),
                    'lastmod' => $post->updated_at?->toAtomString(),
                ];
            });

        Page::query()
            ->published()
            ->select(['slug', 'updated_at'])
            ->latest('updated_at')
            ->get()
            ->each(function (Page $page) use (&$urls): void {
                $urls[] = [
                    'loc' => route('public.pages.show', $page->slug),
                    'lastmod' => $page->updated_at?->toAtomString(),
                ];
            });

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset/>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $url) {
            $node = $xml->addChild('url');
            $node->addChild('loc', htmlspecialchars((string) $url['loc']));

            if ($url['lastmod']) {
                $node->addChild('lastmod', (string) $url['lastmod']);
            }
        }

        return response((string) $xml->asXML(), 200, ['Content-Type' => 'application/xml']);
    }
}
