<?php

namespace Backpack\CRUD\Tests\Unit\Http;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\Database\DatabaseSchema;
use Backpack\CRUD\Tests\BaseTest;
use Backpack\CRUD\Tests\BaseTestClass;

/**
 * @covers Backpack\CRUD\app\Http\Controllers\CrudController
 */
//class CrudControllerTest extends BaseTest
class CrudControllerTestXX extends BaseTestClass
{
//    private $crudPanel;

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        //$this->crudPanel = app('crud');
    }
//    protected function getEnvironmentSetUp($app)
//    {
//        parent::getEnvironmentSetUp($app);
//
//        $controller = '\Backpack\CRUD\Tests\Unit\Http\Controllers\UserCrudController';
//
//        $app['router']->get('users/{id}/edit', "$controller@edit");
//        $app['router']->put('users/{id}', "$controller@update");
//
//        $app['router']->get('admin/users', "$controller@index")->name('admin.users.index');
//
//////        $app->singleton('crud', function ($app) {
////        $app->bind('crud', function ($app) {
////            return new CrudPanel($app);
////        });
//        $app->singleton('crud-single', function ($app) {
////        $this->app->bind('crud', function ($app) {
//            return new CrudPanel($app);
//        });
//
//        $app->bind('crud', function ($app) {
//            $crud = $app->make('crud-single');
//            $crud->clearSettings();
//            return $crud;
//        });
//        $app->scoped('DatabaseSchema', function ($app) {
//            return new DatabaseSchema();
//        });
//
//        $this->crudPanel = app('crud');
//    }

    public function testSetRouteName()
    {
        $crudPanel = app('crud');
        $crudPanel->setRouteName('users');

        $this->assertEquals(url('admin/users'), $crudPanel->getRoute());
    }

    public function testSetRoute()
    {
        $crudPanel = app('crud');
        $crudPanel->setRoute(backpack_url('users'));
        $crudPanel->setEntityNameStrings('singular', 'plural');
        $this->assertEquals(route('users.index'), $crudPanel->getRoute());
    }

    /**
     * @group fail
     */
    public function testCrudRequestUpdatesOnEachRequest()
    {
//        $crud = app('crud');
        // create a first request
//        $firstRequest = request()->create('/users/1/edit', 'GET');
        $firstRequest = request()->create('admin/users/1/edit', 'GET');

        app()->handle($firstRequest);
        $firstRequest = app()->request;

        // see if the first global request has been passed to the CRUD object
//        $this->assertEquals($crud->getRequest(), $firstRequest);
//V7
        $this->assertSame(app('crud')->getRequest(), $firstRequest);


        // create a second request
//        $secondRequest = request()->create('/users/1', 'PUT', ['name' => 'foo']);
        $secondRequest = request()->create('admin/users/1', 'PUT', ['name' => 'foo']);
        app()->handle($secondRequest);
        $secondRequest = app()->request;

        // see if the second global request has been passed to the CRUD object
        $this->assertSame(app()->request, request());
        $this->assertSame(app('crud')->getRequest(), $firstRequest);
        $this->assertSame(app('crud')->getRequest(), $secondRequest);
//        $this->assertEqualsCanonicalizing(app('crud')->getRequest(), $secondRequest);

        // the CRUD object's request should no longer hold the first request, but the second one
        $this->assertNotSame(app('crud')->getRequest(), $firstRequest);
    }
}
