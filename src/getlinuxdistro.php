function getLinuxDistro()
{
    // Declare Linux distros(extensible list).  Order is important (see Ubuntu).
    $distros = array(
        "Arch" => array("release" => "arch-release", "regex" => null),
        "Ubuntu" => array("release" => "issue", "regex" => "/^Ubuntu/"),
        "Debian" => array("release" => "debian_version", "regex" => null),
        "Fedora" => array("release" => "fedora-release", "regex" => null),
        "ClearOS" => array("release" => "clearos-release", "regex" => null),
        "CentOS" => array("release" => "centos-release", "regex" => null),
        "Mageia" => array("release" => "mageia-release", "regex" => null),
        "Redhat" => array("release" => "redhat-release", "regex" => null)
    );

    // Iterate over distros array, check if release file exists.
    // Optionally, run regex on contents.
    foreach ($distros as $distro => $info) {
        if (! file_exists("/etc/{$info["release"]}")) continue;
        if ($info["regex"] != null) {
            $contents = file_get_contents("/etc/{$info["release"]}");
            if (! preg_match($info["regex"], $contents)) continue;
        }

        return $distro;
    }

    return "Unknown";
}

echo getLinuxDistro();

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
