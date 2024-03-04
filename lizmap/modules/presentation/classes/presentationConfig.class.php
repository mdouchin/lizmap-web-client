<?php
/**
 * Manage and give access to lizmap configuration.
 *
 * @author    3liz
 * @copyright 2021 3liz
 *
 * @see      http://3liz.com
 *
 * @license Mozilla Public License : http://www.mozilla.org/MPL/
 */
class presentationConfig
{
    private $status = false;
    private $errors = array();
    private $repository;
    private $project;
    private $lproj;
    private $config;

    public function __construct($repository, $project)
    {
        try {
            $lproj = lizmap::getProject($repository.'~'.$project);
            if (!$lproj) {
                $this->errors = array(
                    'title' => 'Invalid Query Parameter',
                    'detail' => 'The lizmap project '.strtoupper($project).' does not exist !',
                );

                return false;
            }
        } catch (\Lizmap\Project\UnknownLizmapProjectException $e) {
            $this->errors = array(
                'title' => 'Invalid Query Parameter',
                'detail' => 'The lizmap project '.strtoupper($project).' does not exist !',
            );

            return false;
        }

        // Check acl
        if (!$lproj->checkAcl()) {
            $this->errors = array(
                'title' => 'Access Denied',
                'detail' => jLocale::get('view~default.repository.access.denied'),
            );

            return false;
        }

        // presentation config may be an empty array
        $this->repository = $repository;
        $this->project = $project;
        $this->lproj = $lproj;
        $this->status = true;
        $this->config = $this->getPresentations();
    }

    /**
     * Get the presentations stored in the database
     * for the current Lizmap project.
     *
     * @return null|json $presentations List of presentations
     */
    private function getPresentations()
    {
        $dao = \jDao::get('presentation~presentation');
        $getPresentations = $dao->findAll();

        return $getPresentations->fetchAllAssociative();
    }

    /**
     * Get presentation configuration.
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
