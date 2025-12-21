<?php
declare(strict_types=1);

/* FPDF 1.82 - http://www.fpdf.org */
class FPDF
{
    public const FPDF_VERSION = '1.82';
    protected $page;
    protected $n;
    protected $offsets;
    protected $buffer;
    protected $pages;
    protected $state;
    protected $compress;
    protected $k;
    protected $DefOrientation;
    protected $CurOrientation;
    protected $StdPageSizes;
    protected $DefPageSize;
    protected $CurPageSize;
    protected $PageInfo;
    protected $wPt;
    protected $hPt;
    protected $w;
    protected $h;
    protected $lMargin;
    protected $tMargin;
    protected $rMargin;
    protected $bMargin;
    protected $cMargin;
    protected $x;
    protected $y;
    protected $lasth;
    protected $LineWidth;
    protected $fontpath;
    protected $CoreFonts;
    protected $fonts;
    protected $FontFiles;
    protected $diffs;
    protected $images;
    protected $links;
    protected $InHeader;
    protected $InFooter;
    protected $FontFamily;
    protected $FontStyle;
    protected $FontSizePt;
    protected $FontSize;
    protected $CurrentFont;
    protected $DrawColor;
    protected $FillColor;
    protected $TextColor;
    protected $ColorFlag;
    protected $ws;
    protected $AutoPageBreak;
    protected $PageBreakTrigger;
    protected $InHeaderSave;
    protected $InFooterSave;
    protected $zoomMode;
    protected $layoutMode;
    protected $title;
    protected $subject;
    protected $author;
    protected $keywords;
    protected $creator;
    protected $AliasNbPages;
    protected $offset;

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        $this->page = 0;
        $this->n = 2;
        $this->offsets = [];
        $this->buffer = '';
        $this->pages = [];
        $this->PageInfo = [];
        $this->fonts = [];
        $this->FontFiles = [];
        $this->diffs = [];
        $this->images = [];
        $this->links = [];
        $this->InHeader = false;
        $this->InFooter = false;
        $this->state = 0;
        $this->lasth = 0;
        $this->FontFamily = '';
        $this->FontStyle = '';
        $this->FontSizePt = 12;
        $this->FontSize = 12;
        $this->DrawColor = '0 G';
        $this->FillColor = '0 g';
        $this->TextColor = '0 g';
        $this->ColorFlag = false;
        $this->ws = 0;
        $this->zoomMode = 'default';
        $this->layoutMode = 'default';
        $this->title = '';
        $this->subject = '';
        $this->author = '';
        $this->keywords = '';
        $this->creator = '';
        $this->AliasNbPages = '{nb}';
        $this->offset = 0;

        $this->CoreFonts = [
            'courier' => 'Courier',
            'courierB' => 'Courier-Bold',
            'courierI' => 'Courier-Oblique',
            'courierBI' => 'Courier-BoldOblique',
            'helvetica' => 'Helvetica',
            'helveticaB' => 'Helvetica-Bold',
            'helveticaI' => 'Helvetica-Oblique',
            'helveticaBI' => 'Helvetica-BoldOblique',
            'times' => 'Times-Roman',
            'timesB' => 'Times-Bold',
            'timesI' => 'Times-Italic',
            'timesBI' => 'Times-BoldItalic',
            'symbol' => 'Symbol',
            'zapfdingbats' => 'ZapfDingbats',
        ];

        $this->StdPageSizes = [
            'a3' => [841.89, 1190.55],
            'a4' => [595.28, 841.89],
            'a5' => [420.94, 595.28],
            'letter' => [612, 792],
            'legal' => [612, 1008],
        ];

        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->StdPageSizes[$size])) {
                throw new Exception('Unknown page size: ' . $size);
            }
            $size = $this->StdPageSizes[$size];
        }

        if ($unit === 'pt') {
            $this->k = 1;
        } elseif ($unit === 'mm') {
            $this->k = 72 / 25.4;
        } elseif ($unit === 'cm') {
            $this->k = 72 / 2.54;
        } elseif ($unit === 'in') {
            $this->k = 72;
        } else {
            throw new Exception('Incorrect unit: ' . $unit);
        }

        $this->DefPageSize = [$size[0], $size[1]];
        $this->CurPageSize = $this->DefPageSize;
        $this->DefOrientation = $orientation;
        $this->CurOrientation = $orientation;
        $this->_setPageSize($this->DefPageSize);

        $this->fontpath = __DIR__ . '/';
        $this->lMargin = 10;
        $this->tMargin = 10;
        $this->rMargin = 10;
        $this->bMargin = 10;
        $this->cMargin = 1;
        $this->LineWidth = 0.2;
        $this->SetAutoPageBreak(true, 10);
        $this->SetDisplayMode('default');
        $this->SetCompression(true);
        $this->SetTitle('');
        $this->SetAuthor('');
    }

    public function SetTitle($title): void
    {
        $this->title = $title;
    }

    public function SetAuthor($author): void
    {
        $this->author = $author;
    }

    public function SetDisplayMode($zoom, $layout = 'default'): void
    {
        $this->zoomMode = $zoom;
        $this->layoutMode = $layout;
    }

    public function SetCompression($compress): void
    {
        $this->compress = $compress;
    }

    public function SetMargins($left, $top, $right = null): void
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $right === null ? $left : $right;
    }

    public function SetAutoPageBreak($auto, $margin = 0): void
    {
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h - $margin;
    }

    public function AddPage($orientation = ''): void
    {
        if ($this->state === 0) {
            $this->Open();
        }

        $family = $this->FontFamily;
        $style = $this->FontStyle . ($this->FontStyle !== '' ? ' ' : '');
        $size = $this->FontSizePt;
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;

        if ($this->page > 0) {
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            $this->_endpage();
        }

        $this->_beginpage($orientation);
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->k));
        if ($family) {
            $this->SetFont($family, $style, $size);
        }
        if ($dc !== '0 G') {
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if ($fc !== '0 g') {
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
    }

    public function Open(): void
    {
        $this->state = 1;
    }

    public function SetFont($family, $style = '', $size = 0): void
    {
        $family = strtolower($family);
        if ($family === 'arial') {
            $family = 'helvetica';
        }
        $style = strtoupper($style);
        if ($style === 'IB') {
            $style = 'BI';
        }
        if ($size === 0) {
            $size = $this->FontSizePt;
        }
        if ($this->FontFamily === $family && $this->FontStyle === $style && $this->FontSizePt === $size) {
            return;
        }
        $fontkey = $family . $style;
        if (!isset($this->fonts[$fontkey])) {
            if (!isset($this->CoreFonts[$fontkey])) {
                throw new Exception('Undefined font: ' . $family . ' ' . $style);
            }
            $this->fonts[$fontkey] = [
                'i' => count($this->fonts) + 1,
                'type' => 'core',
                'name' => $this->CoreFonts[$fontkey],
                'up' => -100,
                'ut' => 50,
                'cw' => $this->_getfontwidths($fontkey),
            ];
        }
        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
        $this->CurrentFont = $this->fonts[$fontkey];
        if ($this->page > 0) {
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
        }
    }

    public function SetDrawColor($r, $g = null, $b = null): void
    {
        if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
            $this->DrawColor = sprintf('%.3F G', $r / 255);
        } else {
            $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        }
        if ($this->page > 0) {
            $this->_out($this->DrawColor);
        }
    }

    public function SetFillColor($r, $g = null, $b = null): void
    {
        if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
            $this->FillColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->ColorFlag = $this->FillColor !== $this->TextColor;
        if ($this->page > 0) {
            $this->_out($this->FillColor);
        }
    }

    public function SetTextColor($r, $g = null, $b = null): void
    {
        if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
            $this->TextColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->ColorFlag = $this->FillColor !== $this->TextColor;
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $k = $this->k;
        if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
            $x = $this->x;
            $ws = $this->ws;
            if ($ws > 0) {
                $this->ws = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage($this->CurOrientation);
            $this->x = $x;
            if ($ws > 0) {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $k));
            }
        }
        if ($w === 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $s = '';
        if ($fill || $border === 1) {
            if ($fill) {
                $op = $border === 1 ? 'B' : 'f';
            } else {
                $op = 'S';
            }
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ', $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op);
        }
        if (is_string($border)) {
            $x = $this->x;
            $y = $this->y;
            if (strpos($border, 'L') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, $x * $k, ($this->h - ($y + $h)) * $k);
            }
            if (strpos($border, 'T') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - $y) * $k);
            }
            if (strpos($border, 'R') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
            }
            if (strpos($border, 'B') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - ($y + $h)) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
            }
        }
        if ($txt !== '') {
            $dx = $this->cMargin;
            if ($align === 'R') {
                $dx = $w - $this->cMargin - $this->GetStringWidth($txt);
            } elseif ($align === 'C') {
                $dx = ($w - $this->GetStringWidth($txt)) / 2;
            }
            if ($this->ColorFlag) {
                $s .= 'q ' . $this->TextColor . ' ';
            }
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - ($this->y + 0.5 * $h + 0.3 * $this->FontSize)) * $k, $this->_escape($txt));
            if ($this->ColorFlag) {
                $s .= ' Q';
            }
        }
        if ($s !== '') {
            $this->_out($s);
        }
        $this->lasth = $h;
        if ($ln > 0) {
            $this->y += $h;
            if ($ln === 1) {
                $this->x = $this->lMargin;
            }
        } else {
            $this->x += $w;
        }
        if ($link) {
            $this->Link($this->x - $w, $this->y, $w, $h, $link);
        }
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        $cw = $this->CurrentFont['cw'];
        if ($w === 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }
        $b = 0;
        if ($border) {
            if ($border === 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos($border, 'L') !== false) {
                    $b2 .= 'L';
                }
                if (strpos($border, 'R') !== false) {
                    $b2 .= 'R';
                }
                $b = strpos($border, 'T') !== false ? $b2 . 'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl === 2) {
                    $b = $b2;
                }
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                    $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                } else {
                    $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl === 2) {
                    $b = $b2;
                }
            } else {
                $i++;
            }
        }
        if ($border && $b !== '' && strpos($border, 'B') !== false) {
            $b .= 'B';
        }
        $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    public function Ln($h = null): void
    {
        $this->x = $this->lMargin;
        if ($h === null) {
            $this->y += $this->lasth;
        } else {
            $this->y += $h;
        }
    }

    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
    {
        if (!isset($this->images[$file])) {
            if ($type === '') {
                $pos = strrpos($file, '.');
                if ($pos === false) {
                    throw new Exception('Image file has no extension: ' . $file);
                }
                $type = substr($file, $pos + 1);
            }
            $type = strtolower($type);
            if ($type === 'jpg' || $type === 'jpeg') {
                $info = $this->_parsejpg($file);
            } elseif ($type === 'png') {
                $info = $this->_parsepng($file);
            } else {
                throw new Exception('Unsupported image type: ' . $type);
            }
            $info['i'] = count($this->images) + 1;
            $this->images[$file] = $info;
        } else {
            $info = $this->images[$file];
        }
        if ($w === 0 && $h === 0) {
            $w = $info['w'] / $this->k;
            $h = $info['h'] / $this->k;
        }
        if ($w === 0) {
            $w = $h * $info['w'] / $info['h'];
        }
        if ($h === 0) {
            $h = $w * $info['h'] / $info['w'];
        }
        if ($x === null) {
            $x = $this->x;
        }
        if ($y === null) {
            $y = $this->y;
        }
        $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w * $this->k, $h * $this->k, $x * $this->k, ($this->h - ($y + $h)) * $this->k, $info['i']));
        if ($link) {
            $this->Link($x, $y, $w, $h, $link);
        }
    }

    public function SetX($x): void
    {
        if ($x >= 0) {
            $this->x = $x;
        } else {
            $this->x = $this->w + $x;
        }
    }

    public function SetY($y): void
    {
        $this->x = $this->lMargin;
        if ($y >= 0) {
            $this->y = $y;
        } else {
            $this->y = $this->h + $y;
        }
    }

    public function SetXY($x, $y): void
    {
        $this->SetX($x);
        $this->SetY($y);
    }

    public function GetStringWidth($s)
    {
        $s = (string) $s;
        $cw = $this->CurrentFont['cw'];
        $w = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $w += $cw[$s[$i]] ?? 0;
        }
        return $w * $this->FontSize / 1000;
    }

    public function Link($x, $y, $w, $h, $link)
    {
        $this->links[$link][] = [$this->page, $x * $this->k, $this->hPt - $y * $this->k, $w * $this->k, $h * $this->k];
    }

    public function AcceptPageBreak()
    {
        return $this->AutoPageBreak;
    }

    public function Header()
    {
    }

    public function Footer()
    {
    }

    public function Output($dest = '', $name = '', $isUTF8 = false)
    {
        if ($this->state < 3) {
            $this->Close();
        }
        $dest = strtoupper($dest);
        if ($dest === '') {
            if ($name === '') {
                $name = 'doc.pdf';
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $name . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $this->buffer;
            return '';
        }
        if ($dest === 'S') {
            return $this->buffer;
        }
        if ($dest === 'F') {
            file_put_contents($name, $this->buffer);
            return '';
        }
        if ($dest === 'D') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $this->buffer;
            return '';
        }
        throw new Exception('Incorrect output destination: ' . $dest);
    }

    public function Close(): void
    {
        if ($this->page === 0) {
            $this->AddPage();
        }
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        $this->_endpage();
        $this->_enddoc();
    }

    protected function _setPageSize($size): void
    {
        if ($this->CurOrientation === 'P') {
            $this->wPt = $size[0];
            $this->hPt = $size[1];
        } else {
            $this->wPt = $size[1];
            $this->hPt = $size[0];
        }
        $this->w = $this->wPt / $this->k;
        $this->h = $this->hPt / $this->k;
        $this->PageBreakTrigger = $this->h - $this->bMargin;
    }

    protected function _beginpage($orientation): void
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
        if ($orientation) {
            $orientation = strtoupper($orientation);
            if ($orientation !== $this->CurOrientation) {
                $this->CurOrientation = $orientation;
                $this->_setPageSize($this->CurPageSize);
            }
        }
    }

    protected function _endpage(): void
    {
        $this->state = 1;
    }

    protected function _escape($s)
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    protected function _out($s): void
    {
        if ($this->state === 2) {
            $this->pages[$this->page] .= $s . "\n";
        } else {
            $this->buffer .= $s . "\n";
        }
    }

    protected function _enddoc(): void
    {
        $this->_out('%PDF-1.3');
        $this->_putpages();
        $this->_putresources();
        $this->_putinfo();
        $this->_putcatalog();
        $this->offset = strlen($this->buffer);
        $o = $this->offsets;
        $this->_out('xref');
        $this->_out('0 ' . ($this->n + 1));
        $this->_out('0000000000 65535 f ');
        for ($i = 1; $i <= $this->n; $i++) {
            $this->_out(sprintf('%010d 00000 n ', $o[$i]));
        }
        $this->_out('trailer');
        $this->_out('<<');
        $this->_out('/Size ' . ($this->n + 1));
        $this->_out('/Root 1 0 R');
        $this->_out('/Info ' . ($this->n) . ' 0 R');
        $this->_out('>>');
        $this->_out('startxref');
        $this->_out($this->offset);
        $this->_out('%%EOF');
        $this->state = 3;
    }

    protected function _putpages(): void
    {
        $nb = $this->page;
        $pageNumbers = [];
        for ($n = 1; $n <= $nb; $n++) {
            $this->_newobj();
            $pageNum = $this->n;
            $pageNumbers[] = $pageNum;
            $this->_out('<</Type /Page');
            $this->_out('/Parent 1 0 R');
            $this->_out('/Resources 2 0 R');
            $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->wPt, $this->hPt));
            $this->_out('/Contents ' . ($this->n + 1) . ' 0 R>>');
            $this->_out('endobj');

            $p = $this->pages[$n];
            $this->_newobj();
            $this->_out('<< /Length ' . strlen($p) . ' >>');
            $this->_out('stream');
            $this->_out($p);
            $this->_out('endstream');
            $this->_out('endobj');
        }

        $kids = '';
        foreach ($pageNumbers as $pageNum) {
            $kids .= $pageNum . ' 0 R ';
        }
        $this->_newobj(1);
        $this->_out('<</Type /Pages');
        $this->_out('/Kids [' . trim($kids) . ']');
        $this->_out('/Count ' . $nb);
        $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->wPt, $this->hPt));
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putresources(): void
    {
        $this->_putimages();
        $this->_newobj(2);
        $this->_out('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_out('/Font <<');
        foreach ($this->fonts as $font) {
            $this->_out('/F' . $font['i'] . ' << /Type /Font /Subtype /Type1 /BaseFont /' . $font['name'] . ' >>');
        }
        $this->_out('>>');
        if (!empty($this->images)) {
            $this->_out('/XObject <<');
            foreach ($this->images as $info) {
                $this->_out('/I' . $info['i'] . ' ' . $info['n'] . ' 0 R');
            }
            $this->_out('>>');
        }
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putimages(): void
    {
        foreach ($this->images as $file => $info) {
            if (isset($info['n'])) {
                continue;
            }
            $this->_newobj();
            $info['n'] = $this->n;
            $this->_out('<</Type /XObject');
            $this->_out('/Subtype /Image');
            $this->_out('/Width ' . $info['w']);
            $this->_out('/Height ' . $info['h']);
            $this->_out('/ColorSpace /' . $info['cs']);
            $this->_out('/BitsPerComponent ' . $info['bpc']);
            if (isset($info['f'])) {
                $this->_out('/Filter /' . $info['f']);
            }
            if (isset($info['parms'])) {
                $this->_out($info['parms']);
            }
            $this->_out('/Length ' . strlen($info['data']) . '>>');
            $this->_out('stream');
            $this->_out($info['data']);
            $this->_out('endstream');
            $this->_out('endobj');
            $this->images[$file]['n'] = $info['n'];
        }
    }

    protected function _putinfo(): void
    {
        $this->_newobj();
        $this->_out('<<');
        $this->_out('/Producer ' . $this->_textstring('FPDF ' . self::FPDF_VERSION));
        if ($this->title) {
            $this->_out('/Title ' . $this->_textstring($this->title));
        }
        if ($this->author) {
            $this->_out('/Author ' . $this->_textstring($this->author));
        }
        $this->_out('/CreationDate ' . $this->_textstring('D:' . date('YmdHis')));
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putcatalog(): void
    {
        $this->_newobj();
        $this->_out('<<');
        $this->_out('/Type /Catalog');
        $this->_out('/Pages 1 0 R');
        if ($this->zoomMode === 'fullpage') {
            $this->_out('/OpenAction [3 0 R /Fit]');
        } elseif ($this->zoomMode === 'fullwidth') {
            $this->_out('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->zoomMode === 'real') {
            $this->_out('/OpenAction [3 0 R /XYZ null null 1]');
        }
        if ($this->layoutMode === 'single') {
            $this->_out('/PageLayout /SinglePage');
        } elseif ($this->layoutMode === 'continuous') {
            $this->_out('/PageLayout /OneColumn');
        } elseif ($this->layoutMode === 'two') {
            $this->_out('/PageLayout /TwoColumnLeft');
        }
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _newobj(?int $num = null): void
    {
        if ($num === null) {
            $this->n++;
            $num = $this->n;
        } else {
            $this->n = max($this->n, $num);
        }
        $this->offsets[$num] = strlen($this->buffer);
        $this->_out($num . ' 0 obj');
    }

    protected function _textstring($s)
    {
        return '(' . $this->_escape($s) . ')';
    }

    protected function _parsejpg($file)
    {
        $a = getimagesize($file);
        if (!$a) {
            throw new Exception('Missing or incorrect image file: ' . $file);
        }
        $data = file_get_contents($file);
        return [
            'w' => $a[0],
            'h' => $a[1],
            'cs' => 'DeviceRGB',
            'bpc' => 8,
            'f' => 'DCTDecode',
            'data' => $data,
        ];
    }

    protected function _parsepng($file)
    {
        $a = getimagesize($file);
        if (!$a) {
            throw new Exception('Missing or incorrect image file: ' . $file);
        }
        $data = file_get_contents($file);
        $data = substr($data, 8);
        $data = substr($data, 4);
        $data = substr($data, 4);
        $data = gzuncompress($data);
        return [
            'w' => $a[0],
            'h' => $a[1],
            'cs' => 'DeviceRGB',
            'bpc' => 8,
            'f' => 'FlateDecode',
            'data' => $data,
        ];
    }

    protected function _getfontwidths($fontkey)
    {
        static $cw = null;
        if ($cw !== null) {
            return $cw[$fontkey];
        }
        $cw = [];
        $cw['courier'] = $this->_loadfontwidths('courier');
        $cw['courierB'] = $cw['courier'];
        $cw['courierI'] = $cw['courier'];
        $cw['courierBI'] = $cw['courier'];
        $cw['helvetica'] = $this->_loadfontwidths('helvetica');
        $cw['helveticaB'] = $cw['helvetica'];
        $cw['helveticaI'] = $cw['helvetica'];
        $cw['helveticaBI'] = $cw['helvetica'];
        $cw['times'] = $this->_loadfontwidths('times');
        $cw['timesB'] = $cw['times'];
        $cw['timesI'] = $cw['times'];
        $cw['timesBI'] = $cw['times'];
        $cw['symbol'] = $this->_loadfontwidths('symbol');
        $cw['zapfdingbats'] = $this->_loadfontwidths('zapfdingbats');
        return $cw[$fontkey];
    }

    protected function _loadfontwidths($name)
    {
        $widths = [];
        $file = __DIR__ . '/fpdf_fonts_' . $name . '.php';
        if (file_exists($file)) {
            include $file;
            return $widths;
        }
        for ($i = 0; $i < 256; $i++) {
            $widths[chr($i)] = 600;
        }
        return $widths;
    }
}
