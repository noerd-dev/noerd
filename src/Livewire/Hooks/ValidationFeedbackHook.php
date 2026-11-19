<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Noerd\Attributes\ReportsSaveOutcome;
use Noerd\Support\LayoutFields;
use Noerd\Support\LivewireAction;
use Noerd\Traits\NoerdPage;
use ReflectionMethod;

/**
 * Tells the page how a save ended, so a failed one is never silent: the error
 * of a field is rendered next to the field, which the reader does not see
 * while another tab is open — and the Save button may belong to a hosting
 * page that never learns its embedded detail refused the record.
 *
 * After every save action (store/save/update, the hosting page's
 * `storeDetail-{name}` included, and every method marked
 * #[ReportsSaveOutcome]) the hook dispatches one browser event from the
 * saving component. It bubbles to the page chrome around it (x-noerd::page),
 * embedded or not:
 * - `noerd-validation-failed` with `tabs` — the tabs holding the fields with
 *   an error (LayoutFields::tabsOf(); empty when the layout does not declare
 *   them). The page marks those tabs, opens the first one unless the reader
 *   already looks at one of them, and says next to the Save button that
 *   nothing was saved.
 * - `noerd-validation-passed` — the page drops the marks again.
 *
 * Livewire keeps the error bag across requests and only a passing validate()
 * empties it, so the hook empties it before the save runs: the outcome is the
 * one of THIS save, never an error left by live validation or an earlier
 * action (which would also keep the relation forms from being persisted).
 *
 * A ValidationException thrown by the action is turned into the error bag by
 * Livewire before the finish callback runs, so validate(), validateFromLayout()
 * and addError() are all covered without a line in the component.
 *
 * A built-in save the user may not run is skipped by the WriteGuardHook while
 * the finish callbacks still run — nothing was attempted, so nothing is
 * reported and the errors on screen stay.
 *
 * @internal
 */
final class ValidationFeedbackHook extends NoerdComponentHook
{
    public const FAILED_EVENT = 'noerd-validation-failed';

    public const PASSED_EVENT = 'noerd-validation-passed';

    protected const TRAITS = [NoerdPage::class];

    /** @var array<string, bool> Whether Class::method carries #[ReportsSaveOutcome]. */
    private static array $markedMethods = [];

    public function call($method, $params, $returnEarly, $metadata, $componentContext): ?callable
    {
        $component = $this->component;
        $effective = LivewireAction::effectiveMethod($component, (string) $method, is_array($params) ? $params : []);

        if (! $this->reportsOutcome($effective)) {
            return null;
        }

        // Mirrors the WriteGuardHook, which skips a denied built-in save.
        if (LivewireAction::isSave($effective) && ! $component->canSaveObject()) {
            return null;
        }

        $component->resetErrorBag();

        return function ($return) use ($component): void {
            $errors = $component->getErrorBag();

            if ($errors->isEmpty()) {
                $component->dispatch(self::PASSED_EVENT);

                return;
            }

            $fields = $component->pageLayout['fields'] ?? [];

            $component->dispatch(
                self::FAILED_EVENT,
                tabs: LayoutFields::tabsOf(is_array($fields) ? $fields : [], array_map(strval(...), $errors->keys())),
            );
        };
    }

    private function reportsOutcome(string $method): bool
    {
        if (LivewireAction::isSave($method)) {
            return true;
        }

        $component = $this->component;

        return self::$markedMethods[$component::class . '::' . $method] ??= method_exists($component, $method)
            && (new ReflectionMethod($component, $method))->getAttributes(ReportsSaveOutcome::class) !== [];
    }
}
