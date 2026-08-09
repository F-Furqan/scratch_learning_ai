<script lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineComponent, h } from 'vue';

import type { PropType } from 'vue';
import type { SeoPayload } from '@/types';

export default defineComponent({
    name: 'SeoHead',
    props: {
        seo: {
            type: Object as PropType<SeoPayload>,
            required: true,
        },
    },
    setup(props) {
        return () =>
            h(
                Head,
                {
                    title: props.seo.title,
                },
                () => [
                    h('meta', {
                        'head-key': 'description',
                        name: 'description',
                        content: props.seo.description,
                    }),
                    h('link', {
                        'head-key': 'canonical',
                        rel: 'canonical',
                        href: props.seo.canonical_url,
                    }),
                    h('meta', {
                        'head-key': 'og:title',
                        property: 'og:title',
                        content: props.seo.open_graph.title,
                    }),
                    h('meta', {
                        'head-key': 'og:description',
                        property: 'og:description',
                        content: props.seo.open_graph.description,
                    }),
                    h('meta', {
                        'head-key': 'og:url',
                        property: 'og:url',
                        content: props.seo.canonical_url,
                    }),
                    h('meta', {
                        'head-key': 'og:type',
                        property: 'og:type',
                        content: 'website',
                    }),
                    props.seo.open_graph.image
                        ? h('meta', {
                              'head-key': 'og:image',
                              property: 'og:image',
                              content: props.seo.open_graph.image,
                          })
                        : null,
                    h('meta', {
                        'head-key': 'twitter:card',
                        name: 'twitter:card',
                        content: 'summary_large_image',
                    }),
                    h('meta', {
                        'head-key': 'twitter:title',
                        name: 'twitter:title',
                        content: props.seo.twitter.title,
                    }),
                    h('meta', {
                        'head-key': 'twitter:description',
                        name: 'twitter:description',
                        content: props.seo.twitter.description,
                    }),
                    props.seo.twitter.image
                        ? h('meta', {
                              'head-key': 'twitter:image',
                              name: 'twitter:image',
                              content: props.seo.twitter.image,
                          })
                        : null,
                    ...props.seo.structured_data.map((item, index) =>
                        h(
                            'script',
                            {
                                key: `structured-data-${index}`,
                                'head-key': `structured-data-${index}`,
                                type: 'application/ld+json',
                            },
                            JSON.stringify(item),
                        ),
                    ),
                ],
            );
    },
});
</script>
