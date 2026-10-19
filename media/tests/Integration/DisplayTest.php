<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
});

it('shows covers and files in the table without a query per row', function (): void {
    foreach ([$this->world['subjects'][0], $this->world['subjects'][1]] as $index => $subject) {
        media()->attach($subject, fixtureFile('png', 'cover-'.$index.'.png', 300, 300), 'cover');
        media()->attach($subject, fixtureFile('pdf', 'syllabus-'.$index.'.pdf'), 'attachments');
    }

    $queries = queriesDuring(function () use (&$html): void {
        $html = squish((string) asMember($this->world['admin'])->get('/admin/subjects')->assertOk()->getContent());
    });
    $mediaQueries = array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'gcego_media'));

    expect($html)->toMatch('/<img src="\/media\/[0-9a-z]{26}\/variants\/thumb" alt=""/')
        ->toContain('syllabus-0.pdf')
        ->toMatch('/<a href="\/media\/[0-9a-z]{26}\/download"/')
        ->and(count($mediaQueries))->toBeLessThanOrEqual(2);
});

it('previews media read-only in the infolist', function (): void {
    $subject = $this->world['subjects'][0];
    media()->attach($subject, fixtureFile('png', 'cover.png', 640, 480), 'cover');
    media()->attach($subject, fixtureFile('pdf', 'syllabus.pdf'), 'attachments');
    $html = squish((string) asMember($this->world['admin'])->get('/admin/subjects/'.$subject->id)->assertOk()->getContent());

    expect($html)->toMatch('/<img src="\/media\/[0-9a-z]{26}" alt="" loading="lazy" decoding="async" width="640" height="480"/')
        ->toContain('<iframe src="/media/')
        ->toContain('sandbox="allow-same-origin allow-downloads"')
        ->toContain('application/pdf')
        ->toContain('Download<span class="sr-only">: syllabus.pdf</span>');
});
