<?php
namespace Hostarium;

// Excepctions
use Hostarium\HostariumException;
// tar Requirements
use splitbrain\PHPArchive\Tar;
use splitbrain\PHPArchive\ArchiveIllegalCompressionException;
use splitbrain\PHPArchive\ArchiveIOException;
use splitbrain\PHPArchive\ArchiveCorruptedException;
// VFS Requirements
use Vfs\FileSystem;
use Vfs\Node\Directory;
use Vfs\Node\File;
use Vfs\Exception\RegisteredSchemeException;

class CPMove
{
    protected $archive;
    protected $fs;
    protected $dir;
    protected $file;

    /**
     * @throws HostariumException on failure
     **/
    public function __construct($file)
    {
        // The file needs to be a .tar.gz file
        if (substr($file, -7) !== '.tar.gz')
        {
            throw new HostariumException("File extension must be .tar.gz");    
        }

        $this->file = $file;
        $this->archive = new Tar();
        $this->openArchive($this->file);

        // Create the temporary file system and attempt to mount it
        $this->fs = FileSystem::factory('vfs://');
        try
        {
            if($this->fs->mount() === false)
            {
                throw new HostariumException("Failed to mount VFS");
            }

        }
        catch(RegisteredSchemeException $e)
        {
            throw new HostariumException("Failed to mount VFS");           
        }

        // We need the dir with the .tar.gz stripped out for reading the files later
        $this->dir = str_replace('.tar', '', pathinfo($file)['filename']);
    }

    private function openArchive($file)
    {
        // Attempt to open the file and throw an exception if it's not a tar
        try
        {
            $this->archive->open($file);
        }
        catch(ArchiveIllegalCompressionException $e)
        {
            throw new HostariumException("Invalid cpmove file");
        }
        catch(ArchiveIOException $e)
        {
            throw new HostariumException("Unable to access cpmove file");         
        }
    }

    /**
     * Return the domains associated with the cpmove file
     *
     * @param bool $mainOnly Return only the main domain
     *
     * @throws HostariumException on failure
     *
     * @return array|string An array of the domains or string of the main domain if $mainOnly is true
     **/
    public function getDomains(bool $mainOnly=false)
    {
        // Attempt to extract the file to the vfs
        try
        {
            $this->archive->extract('vfs://main', '', '', '/userdata\/main/');
        }
        catch(ArchiveIOException $e)
        {
            throw new HostariumException("Unable to access cpmove file");
        }
        catch(ArchiveCorruptedException $e)
        {
            throw new HostariumException("cpmove file is corrupted");
        }

        // If getting returns null, it means the file is missing.
        if($this->fs->get('main/' . $this->dir . '/userdata/main') === null)
        {
            throw new HostariumException("Failed to read main file");
        }

        // Parse the file into an array
        $domains = yaml_parse($this->fs->get('main/' . $this->dir . '/userdata/main')->getContent());

        // Tidy up
        $this->fs->get('/')->remove('main');

        if($mainOnly === true)
        {
            return $domains['main_domain'];
        }

        return ['main_domain'    => $domains['main_domain'], 
                'addon_domains'  => $domains['addon_domains'], 
                'parked_domains' => $domains['parked_domains'],
                'sub_domains'    => $domains['sub_domains']];
    }

    /**
     * Return the mySQL databases associated with the cpmove file
     *
     * @throws HostariumException on failure
     *
     * @return array Array of databases. Empty if none exist
     **/
    public function getSQLDatabases()
    {
        $contents = $this->archive->contents();

        $sql = [];
        foreach($contents as $c)
        {
            if(preg_match('/.*\/mysql\/.*\.create/', $c->getPath()) === 1)
            {
                $sql[] = pathinfo($c->getPath())['filename'];
            }
        }

        return $sql;
    }

    /**
     * Return the absolute path to the home directory associated with the cpmove file
     *
     * @throws HostariumException on failure
     *
     * @return string Absolute path to the account's home directory without a trailing slash
     **/
    public function getHomePath()
    {
        // Attempt to extract the file to the vfs
        try
        {
            $this->archive->extract('vfs://homedir', '', '', '/homedir_paths/');
        }
        catch(ArchiveIOException $e)
        {
            throw new HostariumException("Unable to access cpmove file");
        }
        catch(ArchiveCorruptedException $e)
        {
            throw new HostariumException("cpmove file is corrupted");
        }

        // If getting returns null, it means the file is missing.
        if($this->fs->get('homedir/' . $this->dir . '/homedir_paths') === null)
        {
            throw new Hostarium\HostariumException("Failed to get home path");
        }

        $path = $this->fs->get('homedir/' . $this->dir . '/homedir_paths');

        $this->fs->get('/')->remove('homedir');

        return trim($path->getContent());
    }

    /**
     * Return an array of mailboxes associated with the cpmove file
     *
     * @throws HostariumException on failure
     *
     * @return array Array of mailboxes
     **/
    public function getMailboxes()
    {
        $domain = $this->getDomains(true);
        
        $this->openArchive($this->file);
        
        $path   = $this->getHomePath();

        $this->openArchive($this->file);

        try
        {
            $this->archive->extract('vfs://etc', '', '', '/homedir\/etc/');
        }
        catch(ArchiveIOException $e)
        {
            throw new HostariumException("Unable to access cpmove file");
        }
        catch(ArchiveCorruptedException $e)
        {
            throw new HostariumException("cpmove file is corrupted");
        }

        // If getting returns null, it means the file is missing.
        if($this->fs->get('etc/' . $this->dir . '/homedir/etc/' . $domain . '/passwd') === null)
        {
            throw new Hostarium\HostariumException("Failed to get email file");
        }

        $mailboxes = [];

        $fileContents = trim($this->fs->get('etc/' . $this->dir . '/homedir/etc/' . $domain . '/passwd')->getContent());

        // If the passwd file is empty there's no emails, so return an empty array
        if(empty($fileContents))
        {
            return [];
        }
        foreach(explode(PHP_EOL, $fileContents) as $m)
        {
            $parts = explode(':', $m);

            $path = explode('/', $parts[5]);

            $mailboxes[] = end($path) . '@' . $domain;
        }

        return $mailboxes;
    }
}