<?php

namespace Sabre\DAV\PropertyStorage;

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\PropPatch;
use Sabre\DAV\PropFind;
use Sabre\DAV\INode;

class Plugin extends ServerPlugin {

    /**
     * If you only want this plugin to store properties for a limited set of
     * paths, you can use a pathFilter to do this.
     *
     * The pathFilter should be a callable. The callable retrieves a path as
     * its argument, and should return true or false wether it allows
     * properties to be stored.
     *
     * @var callable
     */
    public $pathFilter;

    /**
     * Creates the plugin
     *
     * @param Backend\BackendInterface $backend
     */
    public function __construct(Backend\BackendInterface $backend) {

        $this->backend = $backend;

    }

    /**
     * This initializes the plugin.
     *
     * This function is called by Sabre\DAV\Server, after
     * addPlugin is called.
     *
     * This method should set up the required event subscriptions.
     *
     * @param Server $server
     * @return void
     */
    public function initialize(Server $server) {

        $server->on('propFind',    [$this, 'propFind'], 130);
        $server->on('propPatch',   [$this, 'propPatch'], 300);
        $server->on('afterMove',   [$this, 'afterMove']);
        $server->on('afterUnbind', [$this, 'afterUnbind']);

    }

    /**
     * Called during PROPFIND operations.
     *
     * If there's any requested properties that don't have a value yet, this
     * plugin will look in the property storage backend to find them.
     *
     * @param PropFind $propFind
     * @param INode $node
     * @return void
     */
    public function propFind(PropFind $propFind, INode $node) {

        $path = $propFind->getPath();
        $pathFilter = $this->pathFilter;
        if ($pathFilter && !$pathFilter($path)) return;
        $this->backend->propFind($propFind->getPath(), $propFind);

    }

    /**
     * Called during PROPPATCH operations
     *
     * If there's any updated properties that haven't been stored, the
     * propertystorage backend can handle it.
     *
     * @param string $path
     * @param PropPatch $propPatch
     * @return void
     */
    public function propPatch($path, PropPatch $propPatch) {

        $pathFilter = $this->pathFilter;
        if ($pathFilter && !$pathFilter($path)) return;
        $this->backend->propPatch($path, $propPatch);

    }

    /**
     * Called after a node is deleted.
     *
     * This allows the backend to clean up any properties still in the
     * database.
     *
     * @param string $path
     * @return void
     */
    public function afterUnbind($path) {

        $pathFilter = $this->pathFilter;
        if ($pathFilter && !$pathFilter($path)) return;
        $this->backend->delete($path);

    }

    /**
     * Called after a node is moved.
     *
     * This allows the backend to move all the associated properties.
     *
     * @param string $source
     * @param string $destination
     * @return void
     */
    public function afterMove($source, $destination) {

        $pathFilter = $this->pathFilter;
        if ($pathFilter && !$pathFilter($source)) return;
        // If the destination is filtered, afterUnbind will handle cleaning up
        // the properties.
        if ($pathFilter && !$pathFilter($destination)) return;

        $this->backend->move($source, $destination);

    }
}
