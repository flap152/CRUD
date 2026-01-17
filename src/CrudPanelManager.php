<?php

namespace Backpack\CRUD;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Illuminate\Support\Facades\Facade;

/**
 * CrudPanelManager - Central registry and factory for CRUD panels.
 *
 * This class manages multiple CrudPanel instances across different controllers.
 * It acts as a scoped registry that:
 * - Creates and stores CrudPanel instances for each controller
 * - Tracks which operations have been initialized for each controller
 * - Manages the currently active controller context
 * - Provides methods to retrieve the appropriate CrudPanel based on context
 *
 * This allows multiple CRUD controllers to coexist and share state properly
 * within a single request lifecycle, solving re-entrant issues in browser tests.
 *
 * Backported from Backpack v7 for v4.1.x compatibility.
 */
final class CrudPanelManager
{
    /** @var array<string, CrudPanel> Registry of CrudPanel instances indexed by controller class name */
    private array $cruds = [];

    /** @var array<string, array<string>> Tracks which operations have been initialized for each controller */
    private array $initializedOperations = [];

    /** The currently active controller class name */
    private ?string $currentlyActiveCrudController = null;

    /**
     * Get or create a CrudPanel instance for the given controller.
     *
     * @param  string|object  $controller
     * @return CrudPanel
     */
    public function getCrudPanel($controller): CrudPanel
    {
        $controllerClass = is_string($controller) ? $controller : get_class($controller);

        if (isset($this->cruds[$controllerClass])) {
            return $this->cruds[$controllerClass];
        }

        $instance = new CrudPanel();

        $this->cruds[$controllerClass] = $instance;

        return $this->cruds[$controllerClass];
    }

    /**
     * Store a CrudPanel instance for a specific controller.
     *
     * @param  string  $controller
     * @param  CrudPanel  $crud
     */
    public function storeCrudPanel(string $controller, CrudPanel $crud): void
    {
        $this->cruds[$controller] = $crud;
    }

    /**
     * Check if a CrudPanel exists for the given controller.
     *
     * @param  string  $controller
     * @return bool
     */
    public function hasCrudPanel(string $controller): bool
    {
        return isset($this->cruds[$controller]);
    }

    /**
     * Get the active CrudPanel for a controller, with fallback logic.
     *
     * @param  string  $controller  The controller class name
     * @return CrudPanel The CrudPanel instance, creating one if necessary
     */
    public function getActiveCrudPanel(string $controller): CrudPanel
    {
        if (! isset($this->cruds[$controller])) {
            return $this->getCrudPanel($this->getActiveController() ?? $this->getParentController() ?? $controller);
        }

        return $this->cruds[$controller];
    }

    /**
     * Check if a specific operation has been initialized for a controller.
     *
     * @param  string  $controller
     * @param  string  $operation
     * @return bool
     */
    public function isOperationInitialized(string $controller, string $operation): bool
    {
        return in_array($operation, $this->getInitializedOperations($controller), true);
    }

    /**
     * Record that an operation has been initialized for a controller.
     *
     * @param  string  $controller  The controller class name
     * @param  string|null  $operation  The operation name (e.g., 'list', 'create', 'update')
     */
    public function storeInitializedOperation(string $controller, ?string $operation): void
    {
        if (! $operation) {
            return;
        }
        if (! isset($this->initializedOperations[$controller])) {
            $this->initializedOperations[$controller] = [];
        }
        if (! in_array($operation, $this->initializedOperations[$controller], true)) {
            $this->initializedOperations[$controller][] = $operation;
        }
    }

    /**
     * Get the list of operations that have been initialized for a controller.
     *
     * @param  string  $controller  The controller class name
     * @return array<string> Array of initialized operation names
     */
    public function getInitializedOperations(string $controller): array
    {
        return $this->initializedOperations[$controller] ?? [];
    }

    /**
     * Clear initialized operations for a controller (useful for testing).
     *
     * @param  string|null  $controller  The controller class name, or null to clear all
     */
    public function clearInitializedOperations(?string $controller = null): void
    {
        if ($controller === null) {
            $this->initializedOperations = [];
        } else {
            unset($this->initializedOperations[$controller]);
        }
    }

    /**
     * Get the parent (first registered) controller class name.
     *
     * @return string|null The parent controller class name or null if none exists
     */
    public function getParentController(): ?string
    {
        if (! empty($this->cruds)) {
            return array_key_first($this->cruds);
        }

        return $this->getActiveController();
    }

    /**
     * Set the currently active controller and clear the CRUD facade cache.
     *
     * @param  string  $controller  The controller class name to set as active
     */
    public function setActiveController(string $controller): void
    {
        Facade::clearResolvedInstance('crud');
        $this->currentlyActiveCrudController = $controller;
    }

    /**
     * Get the currently active controller class name.
     *
     * @return string|null The active controller class name or null if none is set
     */
    public function getActiveController(): ?string
    {
        return $this->currentlyActiveCrudController;
    }

    /**
     * Clear the currently active controller.
     */
    public function unsetActiveController(): void
    {
        $this->currentlyActiveCrudController = null;
    }

    /**
     * Intelligently identify and return the appropriate CrudPanel based on context.
     *
     * This method uses multiple strategies to find the correct CrudPanel:
     * 1. Use the currently active controller if set
     * 2. Analyze the call stack to find a CRUD controller in the backtrace
     * 3. Return the first available CrudPanel if any exist
     * 4. Create a default CrudPanel as a last resort
     *
     * @return CrudPanel The identified or created CrudPanel instance
     */
    public function identifyCrudPanel(): CrudPanel
    {
        if ($this->getActiveController()) {
            return $this->getCrudPanel($this->getActiveController());
        }

        // Prioritize explicit controller context by analyzing the call stack
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $controller = null;

        foreach ($trace as $step) {
            if (isset($step['class']) &&
                is_a($step['class'], CrudController::class, true) &&
                $step['class'] !== CrudController::class) {
                $controller = (string) $step['class'];
                break;
            }
        }

        if ($controller) {
            $crudPanel = $this->getActiveCrudPanel($controller);

            return $crudPanel;
        }

        $cruds = $this->getCrudPanels();

        if (! empty($cruds)) {
            $crudPanel = end($cruds);

            return $crudPanel;
        }

        // Last resort: create a default CrudPanel for the base controller
        $this->cruds[CrudController::class] = new CrudPanel();

        return $this->cruds[CrudController::class];
    }

    /**
     * Get all registered CrudPanel instances.
     *
     * @return array<string, CrudPanel> Array of CrudPanel instances indexed by controller class name
     */
    public function getCrudPanels(): array
    {
        return $this->cruds;
    }

    /**
     * Clear all CrudPanel instances (useful for testing).
     */
    public function clearCrudPanels(): void
    {
        $this->cruds = [];
        $this->initializedOperations = [];
        $this->currentlyActiveCrudController = null;
    }
}
