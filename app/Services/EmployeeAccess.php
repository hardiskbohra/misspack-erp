<?php

namespace App\Services;

use App\Models\User;

/**
 * Who may read an employee's record.
 *
 * Written as its own answer, in one place, because "the employee sees their own
 * payslips" is a promise about data and the failure mode is not a wrong number
 * on a screen — it is the person in the next chair reading somebody's salary.
 * Every controller that serves a person's document, payslip or salary row asks
 * this class, so the rule cannot be forgotten on a page somebody adds later.
 *
 * The two roles:
 *
 *   - an **administrator** reads and writes every record (that is the office);
 *   - an **employee** reads exactly their own, and nothing else.
 */
class EmployeeAccess
{
    /** Is the actor allowed to see this person's employment record at all? */
    public function canView(?User $actor, ?User $owner): bool
    {
        if (! $actor || ! $owner) {
            return false;
        }

        return $actor->isAdmin() || (int) $actor->id === (int) $owner->id;
    }

    /** May the actor write to this person's record — upload, verify, pay? */
    public function canManage(?User $actor, ?User $owner): bool
    {
        if (! $actor) {
            return false;
        }

        /* An administrator manages anybody; an employee manages only the two
           parts of their own file that are theirs to manage — their contact
           details and their own uploads — which the controllers ask for with
           their own `canEditOwn` question below. */
        return $actor->isAdmin();
    }

    /**
     * May the actor change this part of their own profile?
     *
     * An employee keeps their address, their mobile number and who to call in an
     * emergency current — those are things only they know. Pay, designation,
     * department, joining date and bank account are the office's record, and an
     * employee editing their own salary bank account is how money goes to the
     * wrong place.
     */
    public function ownEditableFields(): array
    {
        return ['mobile', 'address', 'emergency_contact_name', 'emergency_contact_mobile', 'date_of_birth'];
    }

    /** The fields only the office may write. */
    public function officeOnlyFields(): array
    {
        return [
            'name', 'email', 'role', 'department', 'designation', 'employee_code',
            'date_of_joining', 'employment_type', 'employment_status',
            'pan_number', 'bank_name', 'bank_account_name', 'bank_account_number', 'bank_ifsc',
        ];
    }

    /**
     * Refuse politely, and send the reader somewhere useful.
     *
     * A hard 403 is the right answer for an API; for a person who clicked a
     * stale link it is a wall with no door. A redirect to their own workspace
     * with a sentence says what happened and where they are.
     */
    public function deny(?User $actor, ?User $owner)
    {
        if (! $actor) {
            return redirect()->route('login');
        }

        return redirect()
            ->route('my.dashboard')
            ->with('error', 'That page belongs to somebody else\'s record. This is your own workspace.');
    }
}
