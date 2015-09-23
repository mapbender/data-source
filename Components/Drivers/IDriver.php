<?php


/**
 * Interface IDriver
 */
interface IDriver
{
    /**
     * @param $id
     * @return mixed
     */
    public function get($id);

    /**
     * Save the data
     *
     * @param $data
     * @return mixed
     */
    public function save(DataIte $data);

    /**
     * Remove by ID
     *
     * @param $id
     * @return mixed
     */
    public function remove($id);

    /**
     * Connect to the source
     *
     * @param $url
     * @return mixed
     */
    public function connect($url);

    /**
     * Is the driver connected an ready to interact?
     *
     * @return Boolean
     */
    public function isReady();

    /**
     * Has permission to read?
     *
     * @return Boolean
     */
    public function canRead();

    /**
     * Has permission to write?
     *
     * @return Boolean
     */
    public function canWrite();
}