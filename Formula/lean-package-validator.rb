class LeanPackageValidator < Formula
  desc "A CLI for validating if a project has lean releases"
  homepage "https://github.com/raphaelstolt/lean-package-validator"
  url "https://github.com/raphaelstolt/lean-package-validator/archive/refs/tags/v5.3.0.tar.gz"
  sha256 "23b734dca325751555d55fa301e8663662a074af24ef2005a9278a8b8b8d79c1"
  license "MIT"

  depends_on "php@8.1" => :build
  depends_on "composer" => :build

  def install
    system "composer", "install", "--no-dev", "--optimize-autoloader"

    libexec.install Dir["*"]

    (bin/"lean-package-validator").write_env_script libexec/"bin/lean-package-validator", PATH: "#{Formula["php@8.1"].opt_bin}:$PATH"
  end

  test do
    system "#{bin}/lean-package-validator", "--version"
  end
end
