<?php

use App\Enums\GenerationStatus;

it('ranks statuses and identifies final states', function (GenerationStatus $status, int $rank, bool $final) {
    expect($status->rank())->toBe($rank)
        ->and($status->isFinal())->toBe($final);
})->with([
    [GenerationStatus::Pending, 0, false],
    [GenerationStatus::Queued, 1, false],
    [GenerationStatus::Processing, 2, false],
    [GenerationStatus::Succeeded, 3, true],
    [GenerationStatus::Failed, 3, true],
]);
