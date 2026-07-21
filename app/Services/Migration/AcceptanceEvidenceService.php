<?php

namespace App\Services\Migration;

class AcceptanceEvidenceService
{
    /**
     * @return array<string, mixed>
     */
    public function template(string $rehearsalId): array
    {
        return [
            'rehearsal_id' => $rehearsalId,
            'completed_at' => null,
            'roles' => collect(config('migration-readiness.required_acceptance_roles', []))
                ->mapWithKeys(fn (string $label, string $key) => [
                    $key => [
                        'label' => $label,
                        'status' => 'pending',
                        'tested_by' => null,
                        'tested_at' => null,
                        'evidence' => [],
                        'notes' => null,
                    ],
                ])->all(),
            'approvals' => collect(config('migration-readiness.required_approvals', []))
                ->mapWithKeys(fn (string $label, string $key) => [
                    $key => [
                        'label' => $label,
                        'status' => 'pending',
                        'approved_by' => null,
                        'approved_at' => null,
                        'notes' => null,
                    ],
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     * @return array<string, mixed>
     */
    public function validate(?array $evidence, string $rehearsalId): array
    {
        if (! $evidence) {
            return [
                'status' => 'pending',
                'complete' => false,
                'findings' => ['Role acceptance and owner approvals have not been supplied.'],
                'evidence' => $this->template($rehearsalId),
            ];
        }

        $findings = [];
        $critical = false;

        if (($evidence['rehearsal_id'] ?? null) !== $rehearsalId) {
            $critical = true;
            $findings[] = 'Acceptance evidence belongs to a different rehearsal ID.';
        }

        foreach (config('migration-readiness.required_acceptance_roles', []) as $key => $label) {
            $role = data_get($evidence, 'roles.'.$key);
            $status = strtolower((string) data_get($role, 'status', 'pending'));

            if (! in_array($status, ['pending', 'pass', 'fail'], true)) {
                $critical = true;
                $findings[] = $label.' has an invalid acceptance status.';

                continue;
            }

            if ($status === 'fail') {
                $critical = true;
                $findings[] = $label.' acceptance failed.';
            }

            if ($status !== 'pass') {
                $findings[] = $label.' acceptance is not complete.';

                continue;
            }

            if (blank(data_get($role, 'tested_by'))
                || blank(data_get($role, 'tested_at'))
                || empty(data_get($role, 'evidence', []))) {
                $critical = true;
                $findings[] = $label.' is marked pass without tester, timestamp and evidence references.';
            }
        }

        foreach (config('migration-readiness.required_approvals', []) as $key => $label) {
            $approval = data_get($evidence, 'approvals.'.$key);
            $status = strtolower((string) data_get($approval, 'status', 'pending'));

            if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
                $critical = true;
                $findings[] = $label.' has an invalid approval status.';

                continue;
            }

            if ($status === 'rejected') {
                $critical = true;
                $findings[] = $label.' rejected the rehearsal.';
            }

            if ($status !== 'approved') {
                $findings[] = $label.' approval is pending.';

                continue;
            }

            if (blank(data_get($approval, 'approved_by')) || blank(data_get($approval, 'approved_at'))) {
                $critical = true;
                $findings[] = $label.' is marked approved without approver and timestamp.';
            }
        }

        $allRolesPassed = collect(config('migration-readiness.required_acceptance_roles', []))
            ->keys()
            ->every(fn (string $key) => data_get($evidence, 'roles.'.$key.'.status') === 'pass');
        $allApprovalsPassed = collect(config('migration-readiness.required_approvals', []))
            ->keys()
            ->every(fn (string $key) => data_get($evidence, 'approvals.'.$key.'.status') === 'approved');
        $complete = ! $critical && $allRolesPassed && $allApprovalsPassed;

        return [
            'status' => $critical ? 'critical' : ($complete ? 'pass' : 'pending'),
            'complete' => $complete,
            'findings' => array_values(array_unique($findings)),
            'evidence' => $evidence,
        ];
    }
}
