<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function currentBranchId(): int
    {
        $branchId = request()->user()?->branch_id;

        abort_if(! $branchId, 403, 'Authenticated user must belong to a branch.');

        return (int) $branchId;
    }

    protected function abortUnlessCurrentBranch(?int $branchId): void
    {
        abort_if((int) $branchId !== $this->currentBranchId(), 403, 'This resource does not belong to your branch.');
    }
}
