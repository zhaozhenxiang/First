<?php

/************************************************************
 * Copyright (C), 2017-2018,
 * @FileName: File.php
 * @Author: zzx       Version :   V.1.0.0       Date: 2018/2/10
 * @Description: 关于file的操作的封装
 * Config、Cache依赖该类
 ***********************************************************/
namespace Bin\File;

class File extends \SplFileInfo
{

    /**
     * Construct a new SplFileInfo object
     * @link http://php.net/manual/en/splfileinfo.construct.php
     * @param $file_name
     * @since 5.1.2
     */
    public function __construct($file_name)
    {
        return parent::__construct();
    }

    /**
     * Gets the path without filename
     * @link http://php.net/manual/en/splfileinfo.getpath.php
     * @return string the path to the file.
     * @since 5.1.2
     */
    public function getPath()
    {
        return parent::getPath();
    }

    /**
     * Gets the filename
     * @link http://php.net/manual/en/splfileinfo.getfilename.php
     * @return string The filename.
     * @since 5.1.2
     */
    public function getFilename()
    {
        return parent::getFilename();

    }

    /**
     * Gets the file extension
     * @link http://php.net/manual/en/splfileinfo.getextension.php
     * @return string a string containing the file extension, or an
     * empty string if the file has no extension.
     * @since 5.3.6
     */
    public function getExtension()
    {

        return parent::getExtension();
    }

    /**
     * Gets the base name of the file
     * @link http://php.net/manual/en/splfileinfo.getbasename.php
     * @param string $suffix [optional] <p>
     * Optional suffix to omit from the base name returned.
     * </p>
     * @return string the base name without path information.
     * @since 5.2.2
     */
    public function getBasename($suffix = null)
    {
        return parent::getBasename();
    }

    /**
     * Gets the path to the file
     * @link http://php.net/manual/en/splfileinfo.getpathname.php
     * @return string The path to the file.
     * @since 5.1.2
     */
    public function getPathname()
    {
        return parent::getPathname();
    }

    /**
     * Gets file permissions
     * @link http://php.net/manual/en/splfileinfo.getperms.php
     * @return int the file permissions.
     * @since 5.1.2
     */
    public function getPerms()
    {
        return parent::getPerms();
    }

    /**
     * Gets the inode for the file
     * @link http://php.net/manual/en/splfileinfo.getinode.php
     * @return int the inode number for the filesystem object.
     * @since 5.1.2
     */
    public function getInode()
    {
        return parent::getInode();
    }

    /**
     * Gets file size
     * @link http://php.net/manual/en/splfileinfo.getsize.php
     * @return int The filesize in bytes.
     * @since 5.1.2
     */
    public function getSize()
    {
        return parent::getSize();
    }

    /**
     * Gets the owner of the file
     * @link http://php.net/manual/en/splfileinfo.getowner.php
     * @return int The owner id in numerical format.
     * @since 5.1.2
     */
    public function getOwner()
    {
        return parent::getOwner();
    }

    /**
     * Gets the file group
     * @link http://php.net/manual/en/splfileinfo.getgroup.php
     * @return int The group id in numerical format.
     * @since 5.1.2
     */
    public function getGroup()
    {
        return parent::getGroup();
    }

    /**
     * Gets last access time of the file
     * @link http://php.net/manual/en/splfileinfo.getatime.php
     * @return int the time the file was last accessed.
     * @since 5.1.2
     */
    public function getATime()
    {
        return parent::getATime();
    }

    /**
     * Gets the last modified time
     * @link http://php.net/manual/en/splfileinfo.getmtime.php
     * @return int the last modified time for the file, in a Unix timestamp.
     * @since 5.1.2
     */
    public function getMTime()
    {
        return parent::getMTime();
    }

    /**
     * Gets the inode change time
     * @link http://php.net/manual/en/splfileinfo.getctime.php
     * @return int The last change time, in a Unix timestamp.
     * @since 5.1.2
     */
    public function getCTime()
    {
        return parent::getCTime();
    }

    /**
     * Gets file type
     * @link http://php.net/manual/en/splfileinfo.gettype.php
     * @return string A string representing the type of the entry.
     * May be one of file, link,
     * or dir
     * @since 5.1.2
     */
    public function getType()
    {
        return parent::getType();
    }

    /**
     * Tells if the entry is writable
     * @link http://php.net/manual/en/splfileinfo.iswritable.php
     * @return bool true if writable, false otherwise;
     * @since 5.1.2
     */
    public function isWritable()
    {
        return parent::isWritable();
    }

    /**
     * Tells if file is readable
     * @link http://php.net/manual/en/splfileinfo.isreadable.php
     * @return bool true if readable, false otherwise.
     * @since 5.1.2
     */
    public function isReadable()
    {
        return parent::isReadable();
    }

    /**
     * Tells if the file is executable
     * @link http://php.net/manual/en/splfileinfo.isexecutable.php
     * @return bool true if executable, false otherwise.
     * @since 5.1.2
     */
    public function isExecutable()
    {
        return parent::isExecutable();
    }

    /**
     * Tells if the object references a regular file
     * @link http://php.net/manual/en/splfileinfo.isfile.php
     * @return bool true if the file exists and is a regular file (not a link), false otherwise.
     * @since 5.1.2
     */
    public function isFile()
    {
        return parent::isFile();
    }

    /**
     * Tells if the file is a directory
     * @link http://php.net/manual/en/splfileinfo.isdir.php
     * @return bool true if a directory, false otherwise.
     * @since 5.1.2
     */
    public function isDir()
    {
        return parent::isDir();
    }

    /**
     * Tells if the file is a link
     * @link http://php.net/manual/en/splfileinfo.islink.php
     * @return bool true if the file is a link, false otherwise.
     * @since 5.1.2
     */
    public function isLink()
    {
        return parent::isLink();
    }

    /**
     * Gets the target of a link
     * @link http://php.net/manual/en/splfileinfo.getlinktarget.php
     * @return string the target of the filesystem link.
     * @since 5.2.2
     */
    public function getLinkTarget()
    {
        return parent::getLinkTarget();
    }

    /**
     * Gets absolute path to file
     * @link http://php.net/manual/en/splfileinfo.getrealpath.php
     * @return string the path to the file.
     * @since 5.2.2
     */
    public function getRealPath()
    {
        return parent::getRealPath();
    }

    /**
     * Gets an SplFileInfo object for the file
     * @link http://php.net/manual/en/splfileinfo.getfileinfo.php
     * @param string $class_name [optional] <p>
     * Name of an <b>SplFileInfo</b> derived class to use.
     * </p>
     * @return SplFileInfo An <b>SplFileInfo</b> object created for the file.
     * @since 5.1.2
     */
    public function getFileInfo($class_name = null)
    {
        return parent::getFileInfo();
    }

    /**
     * Gets an SplFileInfo object for the path
     * @link http://php.net/manual/en/splfileinfo.getpathinfo.php
     * @param string $class_name [optional] <p>
     * Name of an <b>SplFileInfo</b> derived class to use.
     * </p>
     * @return SplFileInfo an <b>SplFileInfo</b> object for the parent path of the file.
     * @since 5.1.2
     */
    public function getPathInfo($class_name = null)
    {
        return parent::getPathInfo();
    }

    /**
     * Gets an SplFileObject object for the file
     * @link http://php.net/manual/en/splfileinfo.openfile.php
     * @param string $open_mode [optional] <p>
     * The mode for opening the file. See the <b>fopen</b>
     * documentation for descriptions of possible modes. The default
     * is read only.
     * </p>
     * @param bool $use_include_path [optional] <p>
     * &parameter.use_include_path;
     * </p>
     * @param resource $context [optional] <p>
     * &parameter.context;
     * </p>
     * @return SplFileObject The opened file as an <b>SplFileObject</b> object.
     * @since 5.1.2
     */
    public function openFile($open_mode = 'r', $use_include_path = false, $context = null)
    {
        return parent::openFile();
    }

    /**
     * Sets the class name used with <b>SplFileInfo::openFile</b>
     * @link http://php.net/manual/en/splfileinfo.setfileclass.php
     * @param string $class_name [optional] <p>
     * The class name to use when openFile() is called.
     * </p>
     * @return void
     * @since 5.1.2
     */
    public function setFileClass($class_name = null)
    {
        return parent::setFileClass();
    }

    /**
     * Sets the class used with getFileInfo and getPathInfo
     * @link http://php.net/manual/en/splfileinfo.setinfoclass.php
     * @param string $class_name [optional] <p>
     * The class name to use.
     * </p>
     * @return void
     * @since 5.1.2
     */
    public function setInfoClass($class_name = null)
    {
        return parent::setInfoClass();
    }

    /**
     * Returns the path to the file as a string
     * @link http://php.net/manual/en/splfileinfo.tostring.php
     * @return string the path to the file.
     * @since 5.1.2
     */
    public function __toString()
    {
        return parent::__toString();
    }
}