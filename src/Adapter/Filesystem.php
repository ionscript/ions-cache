<?php

namespace Ions\Cache\Adapter;

use Exception as BaseException;
use GlobIterator;

/**
 * Class Filesystem
 * @package Ions\Cache\Adapter
 */
class Filesystem extends AbstractAdapter
{
    /**
     * @var
     */
    protected $totalSpace;

    /**
     * @var string
     */
    protected $lastFileSpecId = '';

    /**
     * @var string
     */
    protected $lastFileSpec = '';

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (!$options instanceof FilesystemOptions) {
            $options = new FilesystemOptions($options);
        }
        return parent::setOptions($options);
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new FilesystemOptions());
        }
        return $this->options;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        $flags = GlobIterator::SKIP_DOTS | GlobIterator::CURRENT_AS_PATHNAME;
        $dir = $this->getOptions()->getDir();
        $clearFolder = null;
        $clearFolder = function ($dir) use (& $clearFolder, $flags) {
            $it = new GlobIterator($dir . DIRECTORY_SEPARATOR . '*', $flags);
            foreach ($it as $pathname) {
                if ($it->isDir()) {
                    $clearFolder($pathname);
                    rmdir($pathname);
                } else {
                    unlink($pathname);
                }
            }
        };
        $clearFolder($dir);
        return true;
    }

    /**
     * @return bool
     */
    public function clearExpired()
    {
        $options = $this->getOptions();
        if($prefix = $options->getPrefix()) {
            $prefix .= $options->getPrefixDelimiter();
        }
        $flags = GlobIterator::SKIP_DOTS | GlobIterator::CURRENT_AS_PATHNAME;
        $path = $options->getDir() . str_repeat(DIRECTORY_SEPARATOR . $prefix . '*', $options->getDirLevel()) . DIRECTORY_SEPARATOR . $prefix . '*.dat';
        $glob = new GlobIterator($path, $flags);
        $time = time();
        $ttl = $options->getTtl();

        foreach ($glob as $pathname) {

            $mtime = filemtime($pathname);

            if ($time >= $mtime + $ttl) {
                unlink($pathname);
            }
        }

        return true;
    }

    /**
     * @param $prefix
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function clearByPrefix($prefix)
    {
        $namespace = (string)$prefix;
        if ($namespace === '') {
            throw new \InvalidArgumentException('No namespace given');
        }
        $options = $this->getOptions();
        if($prefix) {
            $prefix .= $options->getPrefixDelimiter();
        }
        $flags = GlobIterator::SKIP_DOTS | GlobIterator::CURRENT_AS_PATHNAME;
        $path = $options->getDir() . str_repeat(DIRECTORY_SEPARATOR . $prefix . '*', $options->getDirLevel()) . DIRECTORY_SEPARATOR . $prefix . '*.*';
        $glob = new GlobIterator($path, $flags);

        foreach ($glob as $pathname) {
            unlink($pathname);
        }

        return true;
    }

    /**
     * @return FilesystemIterator
     */
    public function getIterator()
    {
        $options = $this->getOptions();

        if($prefix = $options->getPrefix()) {
            $prefix .= $options->getPrefixDelimiter();
        }

        $path = $options->getDir() . str_repeat(DIRECTORY_SEPARATOR . $prefix . '*', $options->getDirLevel()) . DIRECTORY_SEPARATOR . $prefix . '*.dat';

        return new FilesystemIterator($this, $path, $prefix);
    }

    /**
     * @return bool
     */
    public function optimize()
    {
        $options = $this->getOptions();

        if ($options->getDirLevel()) {
            if($prefix = $options->getPrefix()) {
                $prefix .= $options->getPrefixDelimiter();
            }
            $this->rmDir($options->getDir(), $prefix);
        }

        return true;
    }

    /**
     * @return bool|float
     * @throws \RuntimeException
     */
    public function getTotalSpace()
    {
        if ($this->totalSpace === null) {

            $path = $this->getOptions()->getDir();

            $total = disk_total_space($path);

            if ($total === false) {
                throw new \RuntimeException("Can't detect total space of '{$path}'");
            }

            $this->totalSpace = $total;
        }

        return $this->totalSpace;
    }

    /**
     * @return bool|float
     * @throws \RuntimeException
     */
    public function getAvailableSpace()
    {
        $path = $this->getOptions()->getDir();

        $avail = disk_free_space($path);

        if ($avail === false) {
            throw new \RuntimeException("Can't detect free space of '{$path}'");
        }

        return $avail;
    }

    /**
     * @param $key
     * @param null $success
     * @param null $casToken
     * @return BaseException|null
     */
    public function getItem($key, & $success = null, & $casToken = null)
    {
        $options = $this->getOptions();

        if ($options->getReadable() && $options->getClearStatCache()) {
            clearstatcache();
        }

        $argn = func_num_args();

        if ($argn > 2) {
            return parent::getItem($key, $success, $casToken);
        } elseif ($argn > 1) {
            return parent::getItem($key, $success);
        }

        return parent::getItem($key);
    }

    /**
     * @param $normalizedKey
     * @param null $success
     * @param null $casToken
     * @return bool|string|void
     * @throws BaseException
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        if (!$this->internalHasItem($normalizedKey)) {
            $success = false;
            return;
        }

        try {
            $filespec = $this->getFileSpec($normalizedKey);

            $data = $this->getFileContent($filespec . '.dat');

            if (func_num_args() > 2) {
                $casToken = filemtime($filespec . '.dat') . filesize($filespec . '.dat');
            }

            $success = true;

            return $data;
        } catch (BaseException $e) {
            $success = false;
            throw $e;
        }
    }

    /**
     * @param $key
     * @return bool|BaseException|mixed|null
     */
    public function hasItem($key)
    {
        $options = $this->getOptions();

        if ($options->getReadable() && $options->getClearStatCache()) {
            clearstatcache();
        }

        return parent::hasItem($key);
    }

    /**
     * @param $normalizedKey
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $file = $this->getFileSpec($normalizedKey) . '.dat';

        if (!file_exists($file)) {
            return false;
        }

        $ttl = $this->getOptions()->getTtl();

        if ($ttl) {
            $mtime = filemtime($file);

            if (!$mtime) {
                throw new \RuntimeException("Error getting mtime of file '{$file}'");
            }

            if (time() >= ($mtime + $ttl)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $key
     * @return array|bool|BaseException
     */
    public function getMetadata($key)
    {
        $options = $this->getOptions();
        if ($options->getReadable() && $options->getClearStatCache()) {
            clearstatcache();
        }
        return parent::getMetadata($key);
    }

    /**
     * @param $normalizedKey
     * @return array|bool
     */
    protected function internalGetMetadata(& $normalizedKey)
    {
        if (!$this->internalHasItem($normalizedKey)) {
            return false;
        }

        $options = $this->getOptions();

        $filespec = $this->getFileSpec($normalizedKey);

        $file = $filespec . '.dat';

        $metadata = ['filespec' => $filespec, 'mtime' => filemtime($file)];

        if (!$options->getNoCtime()) {
            $metadata['ctime'] = filectime($file);
        }

        if (!$options->getNoAtime()) {
            $metadata['atime'] = fileatime($file);
        }

        return $metadata;
    }

    /**
     * @param $key
     * @param $value
     * @return bool|BaseException
     */
    public function setItem($key, $value)
    {
        $options = $this->getOptions();
        if ($options->getWritable() && $options->getClearStatCache()) {
            clearstatcache();
        }

        return parent::setItem($key, $value);
    }

    /**
     * @param $key
     * @param $value
     * @return bool|BaseException|mixed
     */
    public function addItem($key, $value)
    {
        $options = $this->getOptions();

        if ($options->getWritable() && $options->getClearStatCache()) {
            clearstatcache();
        }

        return parent::addItem($key, $value);
    }

    /**
     * @param $key
     * @param $value
     * @return bool|BaseException|mixed
     */
    public function replaceItem($key, $value)
    {
        $options = $this->getOptions();

        if ($options->getWritable() && $options->getClearStatCache()) {
            clearstatcache();
        }

        return parent::replaceItem($key, $value);
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $filespec = $this->getFileSpec($normalizedKey);

        $this->prepareDirectoryStructure($filespec);

        $wouldblock = null;

        $this->putFileContent($filespec . '.dat', $value, true, $wouldblock);

        if ($wouldblock) {
            $this->putFileContent($filespec . '.dat', $value);
        }

        return true;
    }

    /**
     * @param $token
     * @param $key
     * @param $value
     * @return bool|BaseException|mixed
     */
    public function checkAndSetItem($token, $key, $value)
    {
        $options = $this->getOptions();

        if ($options->getWritable() && $options->getClearStatCache()) {
            clearstatcache();
        }

        return parent::checkAndSetItem($token, $key, $value);
    }

    /**
     * @param $token
     * @param $normalizedKey
     * @param $value
     * @return bool
     */
    protected function internalCheckAndSetItem(& $token, & $normalizedKey, & $value)
    {
        if (!$this->internalHasItem($normalizedKey)) {
            return false;
        }

        $file = $this->getFileSpec($normalizedKey) . '.dat';

        $check = filemtime($file) . filesize($file);

        if ($token !== $check) {
            return false;
        }

        return $this->internalSetItem($normalizedKey, $value);
    }

    /**
     * @param $key
     * @return bool|BaseException|mixed
     */
    public function touchItem($key)
    {
        $options = $this->getOptions();
        if ($options->getWritable() && $options->getClearStatCache()) {
            clearstatcache();
        }
        return parent::touchItem($key);
    }

    /**
     * @param $normalizedKey
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalTouchItem(& $normalizedKey)
    {
        if (!$this->internalHasItem($normalizedKey)) {
            return false;
        }

        $filespec = $this->getFileSpec($normalizedKey);
        $touch = touch($filespec . '.dat');

        if (!$touch) {
            throw new \RuntimeException("Error touching file '{$filespec}.dat'");
        }

        return true;
    }

    /**
     * @param $key
     * @return bool|BaseException
     */
    public function removeItem($key)
    {
        $options = $this->getOptions();
        if ($options->getWritable() && $options->getClearStatCache()) {
            clearstatcache();
        }
        return parent::removeItem($key);
    }

    /**
     * @param $normalizedKey
     * @return bool
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $filespec = $this->getFileSpec($normalizedKey);

        if (!file_exists($filespec . '.dat')) {
            return false;
        } else {
            $this->unlink($filespec . '.dat');
        }
        return true;
    }

    /**
     * @param $dir
     * @param $prefix
     * @return bool
     */
    protected function rmDir($dir, $prefix)
    {
        $glob = glob($dir . DIRECTORY_SEPARATOR . $prefix . '*', GLOB_ONLYDIR | GLOB_NOESCAPE | GLOB_NOSORT);

        if (!$glob) {
            return true;
        }

        $ret = true;

        foreach ($glob as $subdir) {
            if ($this->rmDir($subdir, $prefix)) {
                $ret = rmdir($subdir) && $ret;
            } else {
                $ret = false;
            }
        }

        return $ret;
    }

    /**
     * @param $normalizedKey
     * @return string
     */
    protected function getFileSpec($normalizedKey)
    {
        $options = $this->getOptions();

        $prefix = $options->getPrefix();

        if($prefix) {
            $prefix .=  $options->getPrefixDelimiter();
        }

        $path = $options->getDir() . DIRECTORY_SEPARATOR;

        $level = $options->getDirLevel();

        $fileSpecId = $path . $prefix . $normalizedKey . '/' . $level;

        if ($this->lastFileSpecId !== $fileSpecId) {

            if ($level > 0) {
                $hash = md5($normalizedKey);
                for ($i = 0, $max = ($level * 2); $i < $max; $i += 2) {
                    $path .= $prefix . $hash[$i] . $hash[$i + 1] . DIRECTORY_SEPARATOR;
                }
            }

            $this->lastFileSpecId = $fileSpecId;

            $this->lastFileSpec = $path . $prefix . $normalizedKey;
        }
        return $this->lastFileSpec;
    }

    /**
     * @param $file
     * @param bool $nonBlocking
     * @param null $wouldblock
     * @return bool|string
     * @throws \RuntimeException
     */
    protected function getFileContent($file, $nonBlocking = false, & $wouldblock = null)
    {
        $locking = $this->getOptions()->getFileLocking();

        $wouldblock = null;

        if ($locking) {
            $fp = fopen($file, 'rb');
            if ($fp === false) {
                throw new \RuntimeException("Error opening file '{$file}'");
            }
            if ($nonBlocking) {
                $lock = flock($fp, LOCK_SH | LOCK_NB, $wouldblock);
                if ($wouldblock) {
                    fclose($fp);
                    return;
                }
            } else {
                $lock = flock($fp, LOCK_SH);
            }
            if (!$lock) {
                fclose($fp);
                throw new \RuntimeException("Error locking file '{$file}'");
            }
            $res = stream_get_contents($fp);
            if ($res === false) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new \RuntimeException('Error getting stream contents');
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        } else {
            $res = file_get_contents($file, false);
            if ($res === false) {
                throw new \RuntimeException("Error getting file contents for file '{$file}'");
            }
        }
        return $res;
    }

    /**
     * @param $file
     * @throws \RuntimeException
     */
    protected function prepareDirectoryStructure($file)
    {
        $options = $this->getOptions();

        $level = $options->getDirLevel();

        if (!$level) {
            return;
        }

        $pathname = dirname($file);

        if (file_exists($pathname)) {
            return;
        }

        $perm = $options->getDirPermission();

        $umask = $options->getUmask();

        if ($umask !== false && $perm !== false) {
            $perm = $perm & ~$umask;
        }


        if ($perm === false || $level == 1) {
            $umask = ($umask !== false) ? umask($umask) : false;

            $res = mkdir($pathname, ($perm !== false) ? $perm : 0775, true);

            if ($umask !== false) {
                umask($umask);
            }

            if (!$res) {
                if (file_exists($pathname)) {
                    return;
                }

                $oct = ($perm === false) ? '775' : decoct($perm);

                throw new \RuntimeException("mkdir('{$pathname}', 0{$oct}, true) failed");
            }

            if ($perm !== false && !chmod($pathname, $perm)) {
                $oct = decoct($perm);
                throw new \RuntimeException("chmod('{$pathname}', 0{$oct}) failed");
            }
        } else {
            $parts = [];
            $path = $pathname;
            while (!file_exists($path)) {
                array_unshift($parts, basename($path));
                $nextPath = dirname($path);
                if ($nextPath === $path) {
                    break;
                }
                $path = $nextPath;
            }
            foreach ($parts as $part) {
                $path .= DIRECTORY_SEPARATOR . $part;

                $umask = ($umask !== false) ? umask($umask) : false;

                $res = mkdir($path, ($perm === false) ? 0775 : $perm, false);

                if ($umask !== false) {
                    umask($umask);
                }

                if (!$res) {
                    if (file_exists($path)) {
                        continue;
                    }

                    $oct = ($perm === false) ? '775' : decoct($perm);
                    throw new \RuntimeException("mkdir('{$path}', 0{$oct}, false) failed");
                }

                if ($perm !== false && !chmod($path, $perm)) {
                    $oct = decoct($perm);
                    throw new \RuntimeException("chmod('{$path}', 0{$oct}) failed");
                }
            }
        }
    }

    /**
     * @param $file
     * @param $data
     * @param bool $nonBlocking
     * @param null $wouldblock
     * @throws \RuntimeException
     */
    protected function putFileContent($file, $data, $nonBlocking = false, & $wouldblock = null)
    {
        if (!is_string($data)) {
            $data = (string)$data;
        }

        $options = $this->getOptions();
        $locking = $options->getFileLocking();
        $nonBlocking = $locking && $nonBlocking;
        $wouldblock = null;
        $umask = $options->getUmask();
        $perm = $options->getFilePermission();

        if ($umask !== false && $perm !== false) {
            $perm = $perm & ~$umask;
        }

        if ($locking && $nonBlocking) {
            $umask = ($umask !== false) ? umask($umask) : false;
            $fp = fopen($file, 'cb');
            if ($umask) {
                umask($umask);
            }
            if (!$fp) {
                throw new \RuntimeException("Error opening file '{$file}'");
            }
            if ($perm !== false && !chmod($file, $perm)) {
                fclose($fp);
                $oct = decoct($perm);
                throw new \RuntimeException("chmod('{$file}', 0{$oct}) failed");
            }
            if (!flock($fp, LOCK_EX | LOCK_NB, $wouldblock)) {
                fclose($fp);
                if ($wouldblock) {
                    return;
                } else {
                    throw new \RuntimeException("Error locking file '{$file}'");
                }
            }
            if (fwrite($fp, $data) === false) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new \RuntimeException("Error writing file '{$file}'");
            }
            if (!ftruncate($fp, strlen($data))) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new \RuntimeException("Error truncating file '{$file}'");
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        } else {
            $flags = 0;
            if ($locking) {
                $flags = $flags | LOCK_EX;
            }
            $umask = ($umask !== false) ? umask($umask) : false;
            $rs = file_put_contents($file, $data, $flags);
            if ($umask) {
                umask($umask);
            }
            if ($rs === false) {
                throw new \RuntimeException("Error writing file '{$file}'", 0);
            }
            if ($perm !== false && !chmod($file, $perm)) {
                $oct = decoct($perm);
                throw new \RuntimeException("chmod('{$file}', 0{$oct}) failed", 0);
            }
        }
    }

    /**
     * @param $file
     * @throws \RuntimeException
     */
    protected function unlink($file)
    {
        $res = unlink($file);
        if (!$res && file_exists($file)) {
            throw new \RuntimeException("Error unlinking file '{$file}'; file still exists");
        }
    }
}
