<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Browser\Harness;

use FlexibleUx\LexicalBundle\Form\Type\LexicalFormType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Renders the one page the browser tests drive: a form with a `body` field whose options
 * come from the query string, the stored value rendered through `lexical_content` below
 * it, and - after a submission - what the server received.
 *
 * Query parameters: `value` (the stored HTML; a default document when absent, `?value=` for
 * an empty field), `required` (default 1), `placeholder`, `preset`, `toolbar` (comma
 * separated), `height`, `allow_relative_links`, `sanitize`.
 */
final class FixtureController
{
    public const DEFAULT_VALUE = '<p>Hello <strong>bold</strong> and <em>italic</em> text.</p>'
        .'<ul><li>one</li><li>two</li></ul>'
        .'<p style="text-align: center;">centered</p>'
        .'<p><a href="https://example.com" target="_blank" rel="noopener noreferrer">a link</a> to follow</p>'
        .'<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315" title="Video" allowfullscreen></iframe>'
        .'<p>last paragraph</p>';

    public function __construct(
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $query = $request->query;
        $value = $query->has('value') ? (string) $query->get('value') : self::DEFAULT_VALUE;

        $options = [
            'label' => 'Body text',
            'required' => $query->getBoolean('required', true),
        ];
        if ($query->has('placeholder')) {
            $options['attr'] = ['placeholder' => (string) $query->get('placeholder')];
        }
        if ($query->has('preset')) {
            $options['preset'] = (string) $query->get('preset');
        }
        if ($query->has('toolbar')) {
            $options['toolbar'] = explode(',', (string) $query->get('toolbar'));
        }
        if ($query->has('height')) {
            $options['height'] = (string) $query->get('height');
        }
        if ($query->has('allow_relative_links')) {
            $options['allow_relative_links'] = $query->getBoolean('allow_relative_links');
        }
        if ($query->has('sanitize')) {
            $options['sanitize'] = $query->getBoolean('sanitize');
        }

        $form = $this->formFactory
            ->createNamedBuilder('form', FormType::class, ['body' => $value])
            ->add('body', LexicalFormType::class, $options)
            ->getForm();
        $form->handleRequest($request);

        return new Response($this->twig->render('fixture.html.twig', [
            'form' => $form->createView(),
            'value' => $value,
            'submitted' => $form->isSubmitted() ? ($form->getData()['body'] ?? '') : null,
        ]));
    }
}
