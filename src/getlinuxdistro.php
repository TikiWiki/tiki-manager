function getLinuxDistro()
{
    // Declare Linux distros(extensible list).
    $distros = array(
        "Arch" => "arch-release",
        "Debian" => "debian_version",
        "Fedora" => "fedora-release",
        "ClearOS" => "clearos-release",
        "CentOS" => "centos-release",
        "Mageia" => "mageia-release",
        "Redhat" => "redhat-release"
    );

    // Get everything from /etc directory.
    $etcList = scandir("/etc");

    // Loop through /etc results...
    // $OSDistro;
    foreach ($distros as $distroReleaseFile)
    {
        // Loop through list of distros..
        foreach ($etcList as $entry)
        {
            // Match was found.
            if ($distroReleaseFile === $entry)
            {
                // Find distros array key(i.e. Distro name) by value(i.e. distro release file)
                $OSDistro = array_search($distroReleaseFile, $distros);

                break 2; // Break inner and outer loop.
            }
        }
    }

    return $OSDistro;

}

echo getLinuxDistro();

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
