import pathlib
root = pathlib.Path(__file__).resolve().parent
src = (root / "src/app.html").read_text(encoding="utf-8")
lib = (root / "vendor/qrcode.js").read_text(encoding="utf-8")
lib = "// qrcode-generator 1.4.4 · (c) Kazuhiko Arase · MIT-Lizenz\n" + lib.replace("</script", "<\\/script")
(root / "index.html").write_text(src.replace("/*QRLIB*/", lib), encoding="utf-8", newline="\n")
print("ok", len(src)+len(lib))
