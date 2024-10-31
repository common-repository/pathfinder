<?php

include_once('class-pathfinder-pfconnected-tags.php');

class Pathfinder_Script_Controller
{
    protected $namespace = 'wc/v3';

    protected $script_tags = 'script_tags';
    protected $tags = 'pf_tags';
    protected $disconnect = 'disconnect';

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->script_tags,
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_script_tag'),
                'permission_callback' => '__return_true'
            )
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->script_tags,
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'remove_script_tag'),
                'permission_callback' => '__return_true'
            )
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->tags,
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_key'),
                'permission_callback' => '__return_true'
            )
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->tags,
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'remove_key'),
                'permission_callback' => '__return_true'
            )
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->disconnect,
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'disconnect'),
                'permission_callback' => '__return_true'
            )
        );
    }

    function add_script_tag($request)
    {
        $response = new WP_REST_Response();
        $response->set_status(200);
        if (!isset($request['src']) || !isset($request['type'])) {
            $response->set_status(406);
            $response->set_data("empty type or src");
        }
        if (get_option($request['type']) !== false) {
            update_option($request['type'], $request['src']);
        } else {
            add_option($request['type'], $request['src'], '', 'yes');
        }
        $response->set_data($request['type']);

        return [$response];
    }

    function remove_script_tag($request)
    {
        $response = new WP_REST_Response();
        $response->set_status(200);

        if (!isset($request['type'])) {
            $response->set_status(406);
            $response->set_data("empty type");
        }
        $deleted = true;
        if (get_option($request['type'])) {
            $deleted = delete_option($request['type']);
        }

        return ['deleted' => $deleted];
    }

    function add_key($request)
    {
        $response = new WP_REST_Response();
        $response->set_status(200);
        if (!isset($request[Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG])) {
            $response->set_status(406);
            $response->set_data("empty " . Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG);
        }

        if (get_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG) !== false) {
            update_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG,
                $request[Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG]);
        } else {
            add_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG,
                $request[Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG], '', 'no');
        }

        $response->set_data($request['type']);

        return [$response];
    }

    function remove_key($request)
    {
        $response = new WP_REST_Response();
        $response->set_status(200);
        if (!isset($request[Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG])) {
            $response->set_status(406);
            $response->set_data("empty " . Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG);
        }

        $deleted = true;
        if (get_option($request[Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG])) {
            $deleted = delete_option($request[Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG]);
        }

        return ['deleted' => $deleted];
    }

    function disconnect($request)
    {
        $response = new WP_REST_Response();
        $response->set_status(200);
        if (!isset($request[Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG])) {
            $response->set_status(406);
            $response->set_data("empty " . Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG);
        }
        $tag = get_option(Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG);
        if ($tag == $request[Pathfinder_Pfconnected_Tags::PFCONNECTED_TAG]) {
            try {
                include_once ABSPATH . 'wp-admin/includes/file.php';
                $uninstalled = delete_plugins(array(PATHFINDER_PLUGIN));
                $response->set_data(['uninstalled' => $uninstalled]);
            } catch (Exception $exception) {
                $response->set_status(406);
                $response->set_data($exception);
            }
        } else {
            $response->set_status(406);
            $response->set_data("wrong tag / not connected");
        }

        return [$response];
    }
}
