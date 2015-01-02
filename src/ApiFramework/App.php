<?php namespace ApiFramework;

/**
 * App class
 *
 * @package default
 * @author Mangolabs
 */

class App extends Container
{

    /**
     * @var array Default settings
     */
    private $defaultSettings = [
        'debug.queries'     => false,
        'auth.table'        => 'users',
        'auth.username'     => 'email',
        'auth.password'     => 'password',
        'sessions.folder'   => 'storage/sessions/',
        'sessions.ttl'      => 3600,
        'sessions.cookie'   => 'token',
        'reminders.folder'  => 'storage/reminders/',
        'reminders.ttl'     => 3600,
        'reminders.suffix'  => 'reminders_',
        'lang.folder'       => 'static/languages/',
        'request.emulate'   => true,
        'database.type'     => 'mysql',
        'database.server'   => 'localhost',
        'database.name'     => 'test',
        'database.username' => 'root',
        'database.password' => 'root',
        'public.url'        => 'localhost',
        'templates.path'    => 'templates'
    ];

    /**
     * Constructor
     *
     * @param array $userSettings Array of user defined options
     */
    public function __construct ($userSettings = []) {

        // Setup settings
        $this->container['settings'] = array_merge($this->defaultSettings, $userSettings);

        // Share an auth instance
        $this->container['auth'] = $this->share(function ($container) {
            return new Auth($this);
        });

        // Share a lang instance
        $this->container['lang'] = $this->share(function ($container) {
            return new Lang($this);
        });

        // Share a request instance
        $this->container['request'] = $this->share(function ($container) {
            return new Request($this);
        });

        // Share a response instance
        $this->container['response'] = $this->share(function ($container) {
            return new Response($this);
        });

        // Share a router instance
        $this->container['router'] = $this->share(function ($container) {
            return new Router($this);
        });

        // Share a PDO instance
        $this->container['pdo'] = $this->share(function ($container) {
            return new \PDO(
                'mysql:dbname=' . $this->config('database.name') . ';host=' . $this->config('database.server'),
                $this->config('database.username'),
                $this->config('database.password')
            );
        });

        // Share a database instance
        $this->container['db'] = $this->share(function ($container) {
            return new Database ($this);
        });

        // Share a view instance
        $this->container['view'] = $this->share(function ($container) {
            return new View ($this);
        });

        // Share an HTTP instance
        $this->container['http'] = $this->share(function ($container) {
            return new Http ($this);
        });

        // Share a file instance
        $this->container['file'] = $this->share(function ($container) {
            return new File ($this);
        });
    }

    /**
     * Configure application settings
     *
     * @param string|array $name Setting to set or retrieve
     * @param mixed $value If passed, value to apply on the setting
     * @return mixed Value of a setting
     */
    public function config ($name, $value = null) {

        // Check for massive assignaments
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                $this->config($key, $value);
            }
            return true;
        }

        // Assign a new value
        if (isset($value)) {
            $this->container['settings'][$name] = $value;
        }

        // Or return the current value
        return isset($this->container['settings'][$name]) ? $this->container['settings'][$name] : null;
    }

    /**
     * Runs the application
     *
     * @return string Response
     */
    public function run () {

        // Get URL
        $url = $this->request->url();

        // Get route action
        $action = $this->router->getAction($url);

        // Check if the controller exists
        if (!isset($action) || !class_exists($action['class'])) {
            $this->response->error(404, 'Controller not found');
        }

        // Instance model
        $model = null;
        if (isset($action['model'])) {
            if (!class_exists($action['model'])) {
                $this->response->error(404, 'Model not found');
            } else {
                $model = $this->make($action['model']);
            }
        }

        // Create the required controller
        $controller = $this->make($action['class']);

        // Execute the required method
        $response = call_user_func_array(array($controller, $action['method']), $action['params'] ? : []);

        // Return the response in the right format
        return $this->response->output($response);
    }

    /**
     * Makes an instance of a class
     *
     * @param string $className Class name
     * @return object Class instance
     */
    public function make ($className) {

        // Create a reflection to access the class properties
        $reflection = new \ReflectionClass($className);

        // If the class has no constructor, just return a new instance
        $constructor = $reflection->getConstructor();
        if (is_null($constructor)) {
            return new $className;
        }

        // Or get the constructor parameters and instance dependencies
        $dependencies = [];
        $parameters = $reflection->getConstructor()->getParameters();
        foreach ($parameters as $param) {
            $class = $param->getClass();
            if ($class && $class->getName() === 'ApiFramework\App') {

                // If the dependency is the app itself, inject the current instance
                $dependencies[] = $this;
            } else {

                // Otherwise, inject the instantiated dependency or a null value
                $dependencies[] = $class? $this->make($class->name) : 'NULL';
            }
        }

        // Return the class instance
        return $reflection->newInstanceArgs($dependencies);
    }

}