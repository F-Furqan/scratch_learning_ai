<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CreatorGuidelinesController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('creator/Guidelines', [
            'sections' => [
                [
                    'title' => 'Originality and rights',
                    'items' => [
                        'Submit only content you created or have explicit rights to publish.',
                        'Do not upload copied course videos, paid materials, private documents, or copyrighted images without permission.',
                        'Keep source notes, licenses, or permission records for any third-party assets.',
                    ],
                ],
                [
                    'title' => 'Editorial quality',
                    'items' => [
                        'Use clear titles, practical examples, complete explanations, and accurate metadata.',
                        'Courses should include a complete structure, useful lessons, resources, FAQs, and ownership proof.',
                        'Blogs should be educational, safe, and aligned with Scratch Learning topics.',
                    ],
                ],
                [
                    'title' => 'Review workflow',
                    'items' => [
                        'Drafts and revisions go to admin review before they become public.',
                        'Published content remains protected; edits create revision requests and deletion creates delete requests.',
                        'Rejected or changes-requested content stays hidden until admin approval.',
                    ],
                ],
            ],
        ]);
    }
}
