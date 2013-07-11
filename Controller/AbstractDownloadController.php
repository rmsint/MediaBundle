<?php

namespace Symfony\Cmf\Bundle\MediaBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Cmf\Bundle\MediaBundle\BinaryInterface;
use Symfony\Cmf\Bundle\MediaBundle\FileInterface;
use Symfony\Cmf\Bundle\MediaBundle\FileSystemInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller to handle file downloads for things that have a route
 */
abstract class AbstractDownloadController
{
    protected $managerRegistry;
    protected $managerName;
    protected $class;
    protected $rootPath;

    /**
     * @param ManagerRegistry $registry
     * @param string          $managerName
     * @param string          $class       fully qualified class name of file
     * @param string          $rootPath    path where the filesystem is located
     */
    public function __construct(ManagerRegistry $registry, $managerName, $class, $rootPath = '/')
    {
        $this->managerRegistry = $registry;
        $this->managerName     = $managerName;
        $this->class           = $class;
        $this->rootPath        = $rootPath;
    }

    /**
     * Set the managerName to use to get the object manager;
     * if not called, the default manager will be used.
     *
     * @param string $managerName
     */
    public function setManagerName($managerName)
    {
        $this->managerName = $managerName;
    }

    /**
     * Set the class to use to get the file object;
     * if not called, the default class will be used.
     *
     * @param string $class fully qualified class name of file
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * Set the root path were the file system is located;
     * if not called, the default root path will be used.
     *
     * @param string $rootPath
     */
    public function setRootPath($rootPath)
    {
        $this->rootPath = $rootPath;
    }

    /**
     * Get the object manager from the registry, based on the current
     * managerName
     *
     * @return \Doctrine\Common\Persistence\ObjectManager
     */
    protected function getObjectManager()
    {
        return $this->managerRegistry->getManager($this->managerName);
    }

    /**
     * Map the requested path (ie. subpath in the URL) to an id that can
     * be used to lookup the file in the Doctrine store.
     *
     * @param string $path
     *
     * @return string
     */
    abstract protected function mapPathToId($path);

    /**
     * Action to download a document that has a route
     *
     * @param string $id
     */
    public function downloadAction($path)
    {
        $contentDocument = $this->getObjectManager()->find($this->class, $this->mapPathToId($path));

        if (! $contentDocument || ! $contentDocument instanceof FileInterface) {
            throw new NotFoundHttpException('Content is no file');
        }

        if ($contentDocument instanceof BinaryInterface && is_file($contentDocument->getContentAsStream())) {
            $file = $contentDocument->getContentAsStream();
        } elseif ($contentDocument instanceof FileSystemInterface) {
            $file = $contentDocument->getFileSystemPath();
        } else {
            $file = new \SplTempFileObject();
            $file->fwrite($contentDocument->getContentAsString());
            $file->rewind();
        }

        $response = new BinaryFileResponse($file);
        $response->headers->set('Content-Type', $contentDocument->getContentType());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $contentDocument->getName());

        return $response;
    }
}