<?php

namespace App\Services;

use App\Models\CashflowAccount;
use App\Models\CashflowCategory;
use App\Models\CashflowMasterOption;
use App\Models\FeedbackMasterOption;
use App\Models\LeadMasterOption;
use App\Models\OfficeSetting;
use App\Models\OrganisationAddress;
use App\Models\OrganisationBank;
use App\Models\OrganisationContact;
use App\Models\OrganisationSocial;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The settings module's **one list**.
 *
 * Every settings screen draws its rail from this array, and the hub draws its
 * cards from the same array — so an area is defined once (label, icon, what it
 * holds, where it lives, what to count) and can never be in the menu but missing
 * from the hub, or renamed in one and not the other. That is the whole reason
 * this class exists: before it, the organisation profile and the briefing
 * settings were two sidebar items, the cashflow settings were a button on the
 * ledger, and the lead settings were a link on a list, and nothing tied them
 * together.
 *
 * Three things it deliberately does **not** do:
 *
 *   - it does not own any setting. Each area's rules, validation and writes stay
 *     in the module that has always owned them (`OrganisationController`,
 *     `CashflowSettingController`, `LeadSettingController`,
 *     `OfficeBriefingSettingController`, `FeedbackController`) — this is a
 *     directory, not a second writer;
 *   - it does not decide who may look. The settings routes live in the office's
 *     half of the application (`office` middleware), as they always did;
 *   - it does not count anything until it is asked. `counts()` is where the hub's
 *     numbers come from, each one guarded by its own table check and a
 *     `try/catch`, because a half-migrated database must still show the settings
 *     — a hub that 500s on a missing table is worse than a hub with two fewer
 *     badges, and one of the numbers is not worth that.
 *
 * The boundary rule for what belongs here is in `docs/settings-module.md`: a
 * **setting** is a rule or a master list that changes how a module behaves for
 * everybody. A module's own records — a product, a service retainer, a user, a
 * note — are not settings, however editable they look.
 */
final class SettingsDirectory
{
    public const ORGANISATION = 'organisation';
    public const CASHFLOW = 'cashflow';
    public const LEADS = 'leads';
    public const FEEDBACK = 'feedback';
    public const BRIEFINGS = 'briefings';

    /**
     * Every area of the settings module, in the order the rail shows them.
     *
     * `links` are the headings inside an area that a reader is most often
     * looking for — they are what the hub card offers, and what a search for
     * "bank" lands on. `keywords` are the words a person would type for this
     * area rather than its name: nobody searches for "briefings" when they want
     * to turn the emails off.
     *
     * @return array<string, array{
     *     key: string, label: string, icon: string, blurb: string,
     *     holds: list<string>, route: string, active: string,
     *     links: list<array{label: string, route: string, params: array<string, string>}>,
     *     keywords: list<string>,
     *     counts: callable(): array<string, int|null>
     * }>
     */
    public function areas(): array
    {
        return [
            self::ORGANISATION => [
                'key' => self::ORGANISATION,
                'label' => 'Organisation',
                'icon' => 'fas fa-building',
                'blurb' => 'The company itself: the name, GSTIN and address every document prints, and the bank account money moves through.',
                'holds' => [
                    'Company profile, logo and letterhead',
                    'Addresses and branches',
                    'Bank accounts',
                    'Contacts and social links',
                ],
                'route' => 'settings.organisation',
                'active' => 'settings.organisation*',
                'links' => [
                    ['label' => 'Company profile', 'route' => 'settings.organisation', 'params' => ['tab' => 'company']],
                    ['label' => 'Addresses & branches', 'route' => 'settings.organisation', 'params' => ['tab' => 'addresses']],
                    ['label' => 'Bank accounts', 'route' => 'settings.organisation', 'params' => ['tab' => 'banks']],
                ],
                'keywords' => ['company', 'gst', 'gstin', 'pan', 'logo', 'bank', 'ifsc', 'address', 'branch', 'contact', 'social', 'letterhead'],
                'counts' => fn (): array => [
                    'Addresses' => $this->count('organisation_addresses', fn () => OrganisationAddress::query()->count()),
                    'Banks' => $this->count('organisation_banks', fn () => OrganisationBank::query()->count()),
                    'Contacts' => $this->count('organisation_contacts', fn () => OrganisationContact::query()->count()),
                    'Socials' => $this->count('organisation_socials', fn () => OrganisationSocial::query()->count()),
                ],
            ],

            self::CASHFLOW => [
                'key' => self::CASHFLOW,
                'label' => 'Cashflow',
                'icon' => 'fa-solid fa-scale-balanced',
                'blurb' => 'The accounts the ledger is kept in, the categories its rows are grouped by, and the option lists every cashflow form is filled from.',
                'holds' => [
                    'Cash, current and savings accounts',
                    'Income and expense categories',
                    'Payment modes, currencies and the rest of the option lists',
                ],
                'route' => 'settings.cashflow',
                'active' => 'settings.cashflow*',
                'links' => [
                    ['label' => 'Accounts', 'route' => 'settings.cashflow', 'params' => ['tab' => 'accounts']],
                    ['label' => 'Categories', 'route' => 'settings.cashflow', 'params' => ['tab' => 'categories']],
                    ['label' => 'Payment modes', 'route' => 'settings.cashflow', 'params' => ['tab' => 'payment_mode']],
                ],
                'keywords' => ['cashflow', 'account', 'cash', 'bank', 'category', 'payment mode', 'currency', 'expense head', 'ledger', 'opening balance'],
                'counts' => fn (): array => [
                    'Accounts' => $this->count('cashflow_accounts', fn () => CashflowAccount::query()->count()),
                    'Categories' => $this->count('cashflow_categories', fn () => CashflowCategory::query()->count()),
                    'Options' => $this->count('cashflow_master_options', fn () => CashflowMasterOption::query()->count()),
                ],
            ],

            self::LEADS => [
                'key' => self::LEADS,
                'label' => 'Leads',
                'icon' => 'fa-solid fa-bullseye',
                'blurb' => 'The dropdowns a lead is filed with — its status and source, the finish and printing asked for, and the currency a quote was given in.',
                'holds' => [
                    'Status, source and priority',
                    'Finish and printing',
                    'Currency, incoterm and capacity unit',
                ],
                'route' => 'settings.leads',
                'active' => 'settings.leads*',
                'links' => [
                    ['label' => 'Status', 'route' => 'settings.leads', 'params' => ['tab' => 'lead_status']],
                    ['label' => 'Source', 'route' => 'settings.leads', 'params' => ['tab' => 'lead_source']],
                    ['label' => 'Currencies', 'route' => 'settings.leads', 'params' => ['tab' => 'currency']],
                ],
                'keywords' => ['lead', 'status', 'source', 'priority', 'finish', 'printing', 'currency', 'incoterm', 'capacity unit', 'dropdown'],
                'counts' => fn (): array => [
                    'Options' => $this->count('lead_master_options', fn () => LeadMasterOption::query()->count()),
                ],
            ],

            self::FEEDBACK => [
                'key' => self::FEEDBACK,
                'label' => 'Feedback',
                'icon' => 'fa-regular fa-comment-dots',
                'blurb' => 'The scorecard — the lines the client scores, which are also the list the report groups by and the "needs attention" queue scores against.',
                'holds' => [
                    'The questions on the form',
                    'Retire a line without losing the answers that scored it',
                ],
                'route' => 'settings.feedback',
                'active' => 'settings.feedback*',
                'links' => [],
                'keywords' => ['feedback', 'scorecard', 'dimension', 'question', 'rating', 'survey', 'csat', 'nps'],
                'counts' => fn (): array => [
                    'Saved lines' => $this->count('feedback_master_options', fn () => FeedbackMasterOption::query()
                        ->where('group', FeedbackMasterOption::GROUP_DIMENSION)
                        ->count()),
                ],
            ],

            self::BRIEFINGS => [
                'key' => self::BRIEFINGS,
                'label' => 'Briefings',
                'icon' => 'fas fa-bell',
                'blurb' => 'What the office is told about, whether it interrupts, and who is emailed — for the whole organisation, not per login.',
                'holds' => [
                    'The desk on or off, popups and mail',
                    'Which sources raise an alert',
                    'Who is watching, and which desk they hold',
                ],
                'route' => 'settings.briefings',
                'active' => 'settings.briefings*',
                'links' => [],
                'keywords' => ['briefing', 'alert', 'notification', 'bell', 'email', 'watcher', 'digest', 'popup', 'snooze', 'chase'],
                'counts' => fn (): array => [
                    'Sources on' => $this->sourcesOn(),
                ],
            ],
        ];
    }

    /**
     * One area, or null when the key is not one of them.
     *
     * @return array<string, mixed>|null
     */
    public function area(string $key): ?array
    {
        return $this->areas()[$key] ?? null;
    }

    /**
     * The number the hub shows on each card. `null` means "not this database's
     * business" — a table that is not migrated yet — and the card simply omits
     * that badge rather than showing a zero that would be a lie.
     *
     * @return array<string, array<string, int|null>>
     */
    public function counts(): array
    {
        $counts = [];

        foreach ($this->areas() as $key => $area) {
            try {
                $counts[$key] = $area['counts']();
            } catch (Throwable $e) {
                $counts[$key] = [];
            }
        }

        return $counts;
    }

    /**
     * Where a setting is found by name.
     *
     * The hub tells you an area exists once you have opened Settings; this is
     * what finds it when you are three screens deep in a shipment and cannot
     * remember whether the payment modes live under Cashflow or Organisation.
     * Both the areas and the headings inside them are searched, because "bank"
     * is an organisation *heading* and "briefing" is an area nobody types.
     *
     * @return list<array{title: string, subtitle: string, url: string}>
     */
    public function searchHits(string $query, int $limit = 4): array
    {
        $needle = mb_strtolower(trim($query));

        if (mb_strlen($needle) < 2) {
            return [];
        }

        $hits = [];

        foreach ($this->areas() as $area) {
            $haystack = mb_strtolower(implode(' ', array_merge(
                [$area['label'], $area['blurb']],
                $area['holds'],
                $area['keywords'],
            )));

            if (! str_contains($haystack, $needle)) {
                continue;
            }

            /* A heading that matches beats the area that contains it: somebody
               typing "bank" wants the bank accounts, not the organisation page
               they are four clicks away from. */
            $link = null;
            foreach ($area['links'] as $candidate) {
                if (str_contains(mb_strtolower($candidate['label']), $needle)) {
                    $link = $candidate;
                    break;
                }
            }

            $hits[] = [
                'title' => $area['label'].($link ? ' — '.$link['label'] : ' settings'),
                'subtitle' => 'Settings · '.$area['blurb'],
                'url' => route($link['route'] ?? $area['route'], $link['params'] ?? []),
            ];

            if (count($hits) >= $limit) {
                break;
            }
        }

        return $hits;
    }

    /**
     * A count, or null when this database has not got that table yet.
     *
     * Every number on the hub goes through here: the object check first (a
     * partially installed copy may have the table and not the model, or neither),
     * then the table, then the query inside a `try/catch`. Counting must never be
     * the reason a settings screen does not open.
     */
    private function count(string $table, callable $query): ?int
    {
        try {
            if (! Schema::hasTable($table)) {
                return null;
            }

            return (int) $query();
        } catch (Throwable $e) {
            return null;
        }
    }

    /** How many briefing sources are switched on, out of how many there are. */
    private function sourcesOn(): ?int
    {
        try {
            if (! Schema::hasTable('office_settings')) {
                return null;
            }

            $sources = OfficeSetting::briefings()['sources'] ?? [];

            return count(array_filter($sources));
        } catch (Throwable $e) {
            return null;
        }
    }
}
