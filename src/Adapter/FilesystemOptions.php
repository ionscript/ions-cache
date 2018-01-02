<?php

namespace Ions\Cache\Adapter;

/**
 * Class FilesystemOptions
 * @package Ions\Cache\Adapter
 */
class FilesystemOptions extends AdapterOptions
{
    /**
     * @var string
     */
    protected $dir;

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * @var bool
     */
    protected $clearStatCache = true;

    /**
     * @var int
     */
    protected $dirLevel = 1;

    /**
     * @var bool|int
     */
    protected $dirPermission = 0700;

    /**
     * @var bool
     */
    protected $fileLocking = true;

    /**
     * @var bool|int
     */
    protected $filePermission = 0600;

    /**
     * @var string
     */
    protected $prefixDelimiter = '.';

    /**
     * @var bool
     */
    protected $noAtime = true;

    /**
     * @var bool
     */
    protected $noCtime = true;

    /**
     * @var bool
     */
    protected $umask = false;

    /**
     * FilesystemOptions constructor.
     * @param null $options
     */
    public function __construct($options = null)
    {
        if (strpos(strtoupper(PHP_OS),'WIN') === 0) {
            $this->filePermission = false;
            $this->dirPermission = false;
        }

        parent::__construct($options);
    }

    /**
     * @param $dir
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDir($dir)
    {
        if ($dir !== null) {

            if (!is_dir($dir)) {
                throw new \InvalidArgumentException("Cache directory '{$dir}' not found or not a directory");
            } elseif (!is_writable($dir)) {
                throw new \InvalidArgumentException("Cache directory '{$dir}' not writable");
            } elseif (!is_readable($dir)) {
                throw new \InvalidArgumentException("Cache directory '{$dir}' not readable");
            }

            $dir = rtrim(realpath($dir), DIRECTORY_SEPARATOR);
        } else {
            $dir = sys_get_temp_dir();
        }

        $this->dir = $dir;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getDir()
    {
        if ($this->dir === null) {
            $this->setDir(null);
        }

        return $this->dir;
    }

    /**
     * @param $prefix
     * @return $this
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param $prefixDelimiter
     * @return $this
     */
    public function setPrefixDelimiter($prefixDelimiter)
    {
        $prefixDelimiter = (string)$prefixDelimiter;

        $this->prefixDelimiter = $prefixDelimiter;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrefixDelimiter()
    {
        return $this->prefixDelimiter;
    }

    /**
     * @param $clearStatCache
     * @return $this
     */
    public function setClearStatCache($clearStatCache)
    {
        $clearStatCache = (bool)$clearStatCache;
        $this->clearStatCache = $clearStatCache;
        return $this;
    }

    /**
     * @return bool
     */
    public function getClearStatCache()
    {
        return $this->clearStatCache;
    }

    /**
     * @param $dirLevel
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDirLevel($dirLevel)
    {
        $dirLevel = (int)$dirLevel;

        if ($dirLevel < 0 || $dirLevel > 16) {
            throw new \InvalidArgumentException("Directory level '{$dirLevel}' must be between 0 and 16");
        }

        $this->dirLevel = $dirLevel;

        return $this;
    }

    /**
     * @return int
     */
    public function getDirLevel()
    {
        return $this->dirLevel;
    }

    /**
     * @param $dirPermission
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDirPermission($dirPermission)
    {
        if ($dirPermission !== false) {

            if (is_string($dirPermission)) {
                $dirPermission = octdec($dirPermission);
            } else {
                $dirPermission = (int)$dirPermission;
            }

            if (($dirPermission & 0700) !== 0700) {
                throw new \InvalidArgumentException('Invalid directory permission: need permission to execute, read and write by owner');
            }
        }

        if ($this->dirPermission !== $dirPermission) {
            $this->dirPermission = $dirPermission;
        }

        return $this;
    }

    /**
     * @return bool|int
     */
    public function getDirPermission()
    {
        return $this->dirPermission;
    }

    /**
     * @param $fileLocking
     * @return $this
     */
    public function setFileLocking($fileLocking)
    {
        $fileLocking = (bool)$fileLocking;

        $this->fileLocking = $fileLocking;

        return $this;
    }

    /**
     * @return bool
     */
    public function getFileLocking()
    {
        return $this->fileLocking;
    }

    /**
     * @param $filePermission
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setFilePermission($filePermission)
    {
        if ($filePermission !== false) {
            if (is_string($filePermission)) {
                $filePermission = octdec($filePermission);
            } else {
                $filePermission = (int)$filePermission;
            }

            if (($filePermission & 0600) !== 0600) {
                throw new \InvalidArgumentException('Invalid file permission: need permission to read and write by owner');
            } elseif ($filePermission & 0111) {
                throw new \InvalidArgumentException("Invalid file permission: Cache files shouldn't be executable");
            }
        }

        if ($this->filePermission !== $filePermission) {
            $this->filePermission = $filePermission;
        }

        return $this;
    }

    /**
     * @return bool|int
     */
    public function getFilePermission()
    {
        return $this->filePermission;
    }

    /**
     * @param $noAtime
     * @return $this
     */
    public function setNoAtime($noAtime)
    {
        $noAtime = (bool)$noAtime;

        $this->noAtime = $noAtime;

        return $this;
    }

    /**
     * @return bool
     */
    public function getNoAtime()
    {
        return $this->noAtime;
    }

    /**
     * @param $noCtime
     * @return $this
     */
    public function setNoCtime($noCtime)
    {
        $noCtime = (bool)$noCtime;

        $this->noCtime = $noCtime;

        return $this;
    }

    /**
     * @return bool
     */
    public function getNoCtime()
    {
        return $this->noCtime;
    }

    /**
     * @param $umask
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setUmask($umask)
    {
        if ($umask !== false) {

            if (is_string($umask)) {
                $umask = octdec($umask);
            } else {
                $umask = (int)$umask;
            }

            if ($umask & 0700) {
                throw new \InvalidArgumentException('Invalid umask: need permission to execute, read and write by owner');
            }

            //$umask = $umask & ~0002;
            $umask &= ~0002;
        }

        if ($this->umask !== $umask) {
            $this->umask = $umask;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function getUmask()
    {
        return $this->umask;
    }
}
