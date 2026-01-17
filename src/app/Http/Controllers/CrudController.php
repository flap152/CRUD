<?php

namespace Backpack\CRUD\app\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class CrudController extends Controller
{
    use DispatchesJobs, ValidatesRequests;

    /**
     * @var \Backpack\CRUD\app\Library\CrudPanel\CrudPanel
     */
    public $crud;
    public $data = [];

    public function __construct()
    {
        if ($this->crud) {
            return;
        }

        // ---------------------------
        // Create the CrudPanel object
        // ---------------------------
        // Used by developers inside their ProductCrudControllers as
        // $this->crud or using the CRUD facade.
        //
        // It's done inside a middleware closure in order to have
        // the complete request inside the CrudPanel object.
        //
        // The CrudPanelManager ensures each controller gets its own CrudPanel
        // instance, solving re-entrancy issues in browser tests.
        $this->middleware(function ($request, $next) {
            /** @var \Backpack\CRUD\CrudPanelManager $manager */
            $manager = app('CrudManager');

            // Set this controller as the active one
            $manager->setActiveController(static::class);

            // Get the CrudPanel for this specific controller
            $this->crud = $manager->getCrudPanel(static::class);

            // Reset the CrudPanel if it was already initialized (re-entrant request)
            // This prevents state leaking between requests in browser tests
            if ($this->crud->isInitialized()) {
                $this->crud->reset();
            }

            $this->crud->setRequest($request);

            $this->setupDefaults();
            $this->setup();
            $this->setupConfigurationForCurrentOperation();

            // Mark the CrudPanel as initialized for this request
            $this->crud->initialized = true;

            // Clear active controller after setup is complete
            $manager->unsetActiveController();

            return $next($request);
        });
    }

    /**
     * Allow developers to set their configuration options for a CrudPanel.
     */
    public function setup()
    {
    }

    /**
     * Load routes for all operations.
     * Allow developers to load extra routes by creating a method that looks like setupOperationNameRoutes.
     *
     * @param  string  $segment  Name of the current entity (singular).
     * @param  string  $routeName  Route name prefix (ends with .).
     * @param  string  $controller  Name of the current controller.
     */
    public function setupRoutes($segment, $routeName, $controller)
    {
        preg_match_all('/(?<=^|;)setup([^;]+?)Routes(;|$)/', implode(';', get_class_methods($this)), $matches);

        if (count($matches[1])) {
            foreach ($matches[1] as $methodName) {
                $this->{'setup'.$methodName.'Routes'}($segment, $routeName, $controller);
            }
        }
    }

    /**
     * Load defaults for all operations.
     * Allow developers to insert default settings by creating a method
     * that looks like setupOperationNameDefaults.
     */
    protected function setupDefaults()
    {
        preg_match_all('/(?<=^|;)setup([^;]+?)Defaults(;|$)/', implode(';', get_class_methods($this)), $matches);

        if (count($matches[1])) {
            foreach ($matches[1] as $methodName) {
                $this->{'setup'.$methodName.'Defaults'}();
            }
        }
    }

    /**
     * Load configurations for the current operation.
     *
     * Allow developers to insert default settings by creating a method
     * that looks like setupOperationNameOperation (aka setupXxxOperation).
     */
    protected function setupConfigurationForCurrentOperation()
    {
        $operationName = $this->crud->getCurrentOperation();

        $setupClassName = 'setup'.Str::studly($operationName).'Operation';

        /*
         * FIRST, run all Operation Closures for this operation.
         *
         * It's preferred for this to closures first, because
         * (1) setup() is usually higher in a controller than any other method, so it's more intuitive,
         * since the first thing you write is the first thing that is being run;
         * (2) operations use operation closures themselves, inside their setupXxxDefaults(), and
         * you'd like the defaults to be applied before anything you write. That way, anything you
         * write is done after the default, so you can remove default settings, etc;
         */
        $this->crud->applyConfigurationFromSettings($operationName);

        /*
         * THEN, run the corresponding setupXxxOperation if it exists.
         */
        if (method_exists($this, $setupClassName)) {
            $this->{$setupClassName}();
        }
    }
}
