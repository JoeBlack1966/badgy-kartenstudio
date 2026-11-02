import base64, pathlib
root = pathlib.Path(__file__).resolve().parent
src = (root / "src/app.html").read_text(encoding="utf-8")
lib = (root / "vendor/qrcode.js").read_text(encoding="utf-8")
lib = "// qrcode-generator 1.4.4 · (c) Kazuhiko Arase · MIT-Lizenz\n" + lib.replace("</script", "<\\/script")
logo = "data:image/png;base64," + base64.b64encode((root / "assets/logo-cooltourszene-gold.png").read_bytes()).decode()
out = src.replace("/*QRLIB*/", lib).replace("/*LOGO_GOLD*/", logo)
(root / "index.html").write_text(out, encoding="utf-8", newline="\n")
print("ok", len(out))
