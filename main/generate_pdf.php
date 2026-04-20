<?php
/**
 * generate_pdf.php — S&P illico
 * Générateur PDF professionnel (style bancaire)
 *
 * Actions disponibles :
 *   ?action=depot          &id_compte=XXXXX
 *   ?action=retrait        &id_compte=XXXXX
 *   ?action=fiche_client   &id_compte=XXXXX  [&id_client_recherche=XXX-XXX-XXX-X]
 *   ?action=fiche_compte   &id_compte=XXXXX
 *   ?action=fiche_client_seul &id_client=XXX-XXX-XXX-X
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config/connexion.php';

if (file_exists('fpdf/fpdf.php'))         require_once 'fpdf/fpdf.php';
elseif (file_exists('vendor/autoload.php')) require_once 'vendor/autoload.php';
else die('FPDF introuvable. Téléchargez-le depuis http://www.fpdf.org/');

session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); die('Non authentifié'); }

$action     = $_GET['action']                ?? '';
$id_compte  = trim($_GET['id_compte']        ?? '');
$id_client  = trim($_GET['id_client']        ?? '');
$id_client_recherche = trim($_GET['id_client_recherche'] ?? '');

// ════════════════════════════════════════════════════════════════
// HELPERS - GESTION DES ACCENTS
// ════════════════════════════════════════════════════════════════

/**
 * Convertit une chaîne UTF-8 en ISO-8859-1 pour FPDF
 * Préserve les accents et caractères spéciaux
 */
function ct(string $text): string {
    $text = strip_tags((string)$text);
    // Convertir UTF-8 en ISO-8859-1
    $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
    return $converted !== false ? $converted : $text;
}

function fm(float $v, string $d='HTG'): string { 
    return number_format($v,2,',',' ').' '.$d; 
}

function fd($d, bool $t=false): string {
    if (!$d) return '—';
    return date($t?'d/m/Y H:i':'d/m/Y', strtotime($d));
}

function txId(int $id): string { 
    return str_pad($id, 8, '0', STR_PAD_LEFT); 
}

// ════════════════════════════════════════════════════════════════
// CLASSE DE BASE — mise en page commune à tous les PDFs
// ════════════════════════════════════════════════════════════════
class IllicoPDF extends FPDF
{
    // ── Couleurs institutionnelles ──────────────────────────
    const C_BLEU    = [30,  58,  138];  // #1e3a8a  — bleu marine S&P
    const C_BLEU2   = [59,  130, 246];  // #3b82f6  — bleu clair
    const C_VERT    = [22,  163, 74];   // #16a34a
    const C_ROUGE   = [220, 38,  38];   // #dc2626
    const C_GRIS    = [100, 116, 139];  // #64748b
    const C_GRIS_L  = [241, 245, 249];  // #f1f5f9 fond clair
    const C_TEXTE   = [30,  41,  59];   // #1e293b
    const C_LIGNE   = [226, 232, 240];  // #e2e8f0

    // ── Marges ──────────────────────────────────────────────
    const M_L = 15;  // marge gauche
    const M_R = 15;  // marge droite
    const W   = 180; // largeur utile (210 - 30)

    // ── Configuration du logo ───────────────────────────────
    protected $logoPath = '';
    protected $logoWidth = 25;
    protected $logoHeight = 20;
    
    /**
     * Constructeur
     */
    public function __construct($orientation='P', $unit='mm', $size='A4')
    {
        parent::__construct($orientation, $unit, $size);
        // Définir le chemin par défaut du logo (à modifier selon votre structure)
        $this->logoPath = __DIR__ . '/logo.jpeg';
    }
    
    /**
     * Définit le chemin du logo
     */
    public function setLogo(string $path, float $width=25, float $height=20): void {
        if (file_exists($path)) {
            $this->logoPath = $path;
            $this->logoWidth = $width;
            $this->logoHeight = $height;
        }
    }

    /**
     * Surcharge de Cell pour supporter l'UTF-8
     */
    public function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
    {
        if ($txt !== '') {
            $txt = ct($txt);
        }
        parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    }
    
    /**
     * Surcharge de MultiCell pour supporter l'UTF-8
     */
    public function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
    {
        $txt = ct($txt);
        parent::MultiCell($w, $h, $txt, $border, $align, $fill);
    }

    // ── Méthodes publiques ───────────────────────────────────
    
    /**
     * Définit la couleur de texte ou de remplissage
     */
    public function setColor(array $c, bool $fill=false): void {
        [$r,$g,$b] = $c;
        if ($fill) {
            $this->SetFillColor($r,$g,$b);
        } else {
            $this->SetTextColor($r,$g,$b);
        }
    }
    
    /**
     * Définit la couleur de trait
     */
    public function setDraw(array $c): void {
        $this->SetDrawColor($c[0], $c[1], $c[2]);
    }

    /**
     * Ligne horizontale
     */
    public function hLine(?array $color=null, float $lw=0.3): void {
        if ($color === null) $color = self::C_LIGNE;
        $this->SetLineWidth($lw);
        $this->setDraw($color);
        $this->Line(self::M_L, $this->GetY(), 210-self::M_R, $this->GetY());
    }

    /**
     * Pied de page
     */
    public function Footer(): void {
        $this->SetY(-18);
        $this->SetLineWidth(0.3);
        $this->setDraw(self::C_LIGNE);
        $this->Line(self::M_L, $this->GetY(), 210-self::M_R, $this->GetY());
        $this->Ln(3);
        $this->SetFont('Arial','I',7.5);
        $this->setColor(self::C_GRIS);
        $this->Cell(0, 4, 'S&P illico — Banque Communautaire | Document officiel — Conservez ce document', 0, 1, 'C');
        $this->SetFont('Arial','',7);
        $this->Cell(self::W/2, 4, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'L');
        $this->Cell(self::W/2, 4, 'Généré le : '.date('d/m/Y H:i'), 0, 1, 'R');
    }

    /**
     * En-tête principal avec bande colorée et logo
     */
    public function bandeHeader(string $titre, string $sous_titre='', bool $vert=false): void {
        $color = $vert ? self::C_VERT : self::C_BLEU;
        [$r,$g,$b] = $color;

        // Bande colorée (hauteur augmentée pour accueillir le logo)
        $this->SetFillColor($r,$g,$b);
        $this->Rect(0, 0, 210, 45, 'F');

        // ==============================================
        // ESPACE POUR LE LOGO (côté gauche)
        // ==============================================
        $logoX = self::M_L;
        $logoY = 5;
        
        // Tenter d'afficher le logo s'il existe
        if (file_exists($this->logoPath)) {
            // Obtenir les dimensions du logo
            list($width, $height) = @getimagesize($this->logoPath);
            if ($width && $height) {
                $ratio = $width / $height;
                $logoWidth = $this->logoWidth;
                $logoHeight = $logoWidth / $ratio;
                
                // S'assurer que le logo ne dépasse pas
                if ($logoHeight > 35) {
                    $logoHeight = 35;
                    $logoWidth = $logoHeight * $ratio;
                }
                
                $this->Image($this->logoPath, $logoX, $logoY, $logoWidth, $logoHeight);
                $textX = $logoX + $logoWidth + 10;
            } else {
                $textX = $logoX + $this->logoWidth + 10;
            }
        } else {
            // Si pas de logo, afficher un cadre vide
            $this->SetDrawColor(200, 200, 200);
            $this->Rect($logoX, $logoY, $this->logoWidth, $this->logoHeight);
            $this->SetFont('Arial','I',6);
            $this->SetTextColor(150,150,150);
            $this->SetXY($logoX + 2, $logoY + $this->logoHeight/2 - 2);
            $this->Cell($this->logoWidth - 4, 4, 'LOGO', 0, 0, 'C');
            $textX = $logoX + $this->logoWidth + 10;
        }

        // ==============================================
        // TEXTE ET INFORMATIONS (à droite du logo)
        // ==============================================
        
        // Nom de la banque
        $this->SetFont('Arial','B',18);
        $this->SetTextColor(255,255,255);
        $this->SetXY($textX, 8);
        $this->Cell(0, 8, 'S&P illico', 0, 0, 'L');

        // Sous-titre
        $this->SetFont('Arial','',9);
        $this->SetTextColor(200,214,240);
        $this->SetXY($textX, 17);
        $this->Cell(0, 5, 'Banque Communautaire', 0, 1, 'L');

        // Date
        $this->SetFont('Arial','',8);
        $this->SetXY($textX, 23);
        $this->Cell(0, 5, date('d/m/Y H:i'), 0, 1, 'L');

        // ==============================================
        // TITRE DU DOCUMENT (en bas de la bande)
        // ==============================================
        $this->SetFont('Arial','B',14);
        $this->SetTextColor(255,255,255);
        $this->SetXY(self::M_L, 32);
        $this->Cell(self::W, 8, $titre, 0, 0, 'L');

        // Sous-titre du document
        if ($sous_titre) {
            $this->SetFont('Arial','',9);
            $this->SetTextColor(200,220,255);
            $this->SetXY(self::M_L, 40);
            $this->Cell(self::W, 5, $sous_titre, 0, 0, 'L');
        }

        // Position après l'en-tête
        $this->SetXY(self::M_L, 48);
    }

    /**
     * Section titre
     */
    public function sectionTitle(string $txt, ?array $color=null): void {
        if ($color === null) $color = self::C_BLEU;
        $this->Ln(4);
        $this->SetFont('Arial','B',10);
        $this->setColor($color);
        $this->Cell(0, 7, $txt, 0, 1, 'L');
        $this->SetLineWidth(0.5);
        $this->setDraw($color);
        $this->Line(self::M_L, $this->GetY(), 210-self::M_R, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->Ln(3);
    }

    /**
     * Ligne de donnée label:valeur
     */
    public function dataRow(string $label, string $value, bool $highlight=false, ?array $hColor=null, bool $shaded=false): void {
        if ($hColor === null) $hColor = self::C_TEXTE;
        $y = $this->GetY();
        if ($shaded) {
            $this->SetFillColor(248,250,252);
            $this->Rect(self::M_L, $y, self::W, 7, 'F');
        }
        $this->SetFont('Arial','B',9.5);
        $this->setColor(self::C_GRIS);
        $this->SetX(self::M_L);
        $this->Cell(55, 7, $label.' :', 0, 0, 'L');
        $this->SetFont('Arial','',9.5);
        if ($highlight) {
            $this->setColor($hColor);
        } else {
            $this->setColor(self::C_TEXTE);
        }
        $this->Cell(0, 7, $value, 0, 1, 'L');
    }

    /**
     * Boîte montant (grande case visuelle)
     */
public function montantBox(string $label, string $valeur, ?array $bgColor=null): void {
    if ($bgColor === null) $bgColor = self::C_VERT;
    [$r,$g,$b] = $bgColor;

    $y = $this->GetY() + 3;
    $this->SetFillColor($r,$g,$b);
    $this->Rect(self::M_L, $y, self::W, 12, 'F');

    $this->SetFont('Arial','B',11);
    $this->SetTextColor(255,255,255);

    $this->SetXY(self::M_L+5, $y+3);

    // Label à gauche
    $this->Cell((self::W-10)/2, 6, strtoupper($label), 0, 0, 'L');

    // Montant à droite
    $this->Cell((self::W-10)/2, 6, $valeur, 0, 1, 'R');

    $this->Ln(5);
}
    /**
     * Boîte de statut (badge)
     */
    public function badge(string $txt, array $bgColor): void {
        [$r,$g,$b] = $bgColor;
        $x = $this->GetX(); 
        $y = $this->GetY();
        $w = $this->GetStringWidth($txt) + 8;
        $this->SetFillColor($r,$g,$b);
        $this->Rect($x, $y, $w, 6, 'F');
        $this->SetFont('Arial','B',8);
        $this->SetTextColor(255,255,255);
        $this->Cell($w, 6, $txt, 0, 0, 'C');
    }

    /**
     * Ligne de signature
     */
    public function signaturesBlock(string $left='Signature du client', string $right='Signature & Cachet agent'): void {
        $this->Ln(12);
        $y = $this->GetY();
        $this->SetLineWidth(0.4);
        $this->setDraw(self::C_TEXTE);
        $this->Line(self::M_L, $y, self::M_L+70, $y);
        $this->Line(125, $y, 125+70, $y);
        $this->SetFont('Arial','',8.5);
        $this->setColor(self::C_GRIS);
        $this->SetXY(self::M_L, $y+2);
        $this->Cell(70, 5, $left, 0, 0, 'C');
        $this->SetXY(125, $y+2);
        $this->Cell(70, 5, $right, 0, 1, 'C');
    }

    /**
     * Encadré d'information (pour numéro de référence)
     */
    public function refBox(string $ref, string $label='Référence transaction'): void {
        $this->SetFont('Arial','',8);
        $this->setColor(self::C_GRIS);
        $this->SetX(self::M_L);
        $this->Cell(self::W, 5, $label.' : #'.$ref, 0, 1, 'R');
    }
}


// ════════════════════════════════════════════════════════════════
// PDF REÇU DÉPÔT
// ════════════════════════════════════════════════════════════════
function genDepot(PDO $pdo, string $id_compte): void
{
    // Récupérer la dernière transaction de dépôt
    $stmt = $pdo->prepare("
        SELECT t.*, c.id_compte, c.devise,
               CONCAT(cl.prenom,' ',cl.nom) AS titulaire,
               cl.id_client, cl.telephone,
               tc.nom AS type_compte,
               u.nom_complet AS operateur,
               s.nom AS succursale, s.code AS succ_code,
               s.telephone AS succ_tel, s.email AS succ_email
        FROM transactions t
        JOIN comptes c  ON t.compte_id = c.id
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN utilisateurs u   ON t.utilisateur_id = u.id
        JOIN succursales s    ON t.succursale_id = s.id
        WHERE c.id_compte = ? AND t.type = 'depot'
        ORDER BY t.date_transaction DESC LIMIT 1
    ");
    $stmt->execute([$id_compte]);
    $d = $stmt->fetch();
    if (!$d) { http_response_code(404); die('Transaction introuvable'); }

    $pdf = new IllicoPDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(IllicoPDF::M_L, 45, IllicoPDF::M_R);
    $pdf->SetAutoPageBreak(true, 25);

    // En-tête
    $pdf->bandeHeader('RECU DE DEPOT', 'Succursale : '.$d['succ_code'].' — '.$d['succursale']);

    // Référence
    $pdf->refBox(txId((int)$d['id']));

    // Boîte montant
    $pdf->montantBox('Montant déposé', '+ '.fm((float)$d['montant'], $d['devise']), IllicoPDF::C_VERT);

    // Informations de la transaction
    $pdf->sectionTitle('INFORMATIONS DE LA TRANSACTION');
    $rows = [
        ['Date & heure',    fd($d['date_transaction'], true)],
        ['N° de compte',    $d['id_compte']],
        ['NIF / CINU',      $d['id_client']],
        ['Titulaire',       $d['titulaire']],
        ['Type de compte',  $d['type_compte']],
        ['Téléphone',       $d['telephone'] ?: '—'],
        ['Description',     $d['description'] ?: 'Dépôt en espèces'],
    ];
    foreach ($rows as $i => $row)
        $pdf->dataRow($row[0], $row[1], false, IllicoPDF::C_TEXTE, $i%2===0);

    // Récapitulatif solde
    $pdf->sectionTitle('RÉCAPITULATIF DU SOLDE');
    $pdf->dataRow('Solde avant dépôt',  fm((float)$d['solde_avant'],  $d['devise']), false, IllicoPDF::C_TEXTE, true);
    $pdf->dataRow('Montant déposé',     '+ '.fm((float)$d['montant'], $d['devise']), true,  IllicoPDF::C_VERT,  false);
    
    // Ligne de total
    $pdf->SetLineWidth(0.6);
    $pdf->setDraw(IllicoPDF::C_VERT);
    $pdf->Line(IllicoPDF::M_L, $pdf->GetY(), 210-IllicoPDF::M_R, $pdf->GetY());
    $pdf->SetLineWidth(0.2);
    $pdf->SetFont('Arial','B',11);
    $pdf->setColor(IllicoPDF::C_VERT);
    $pdf->SetX(IllicoPDF::M_L);
    $y0 = $pdf->GetY()+1;
    $pdf->SetFillColor(235,253,245);
    $pdf->Rect(IllicoPDF::M_L, $y0, IllicoPDF::W, 9, 'F');
    $pdf->SetXY(IllicoPDF::M_L, $y0);
    $pdf->Cell(55, 9, 'NOUVEAU SOLDE :', 0, 0, 'L');
    $pdf->Cell(0,  9, fm((float)$d['solde_apres'], $d['devise']), 0, 1, 'R');

    // Opérateur
    $pdf->Ln(3);
    $pdf->sectionTitle('INFORMATIONS DE L\'AGENT');
    $pdf->dataRow('Opérateur',  $d['operateur'], false, IllicoPDF::C_TEXTE, true);
    $pdf->dataRow('Succursale', $d['succ_code'].' — '.$d['succursale'], false, IllicoPDF::C_TEXTE, false);
    if ($d['succ_tel'])   $pdf->dataRow('Téléphone agence', $d['succ_tel'],   false, IllicoPDF::C_TEXTE, true);
    if ($d['succ_email']) $pdf->dataRow('Email agence',     $d['succ_email'], false, IllicoPDF::C_TEXTE, false);

    // Signatures
    $pdf->signaturesBlock('Signature du client', 'Signature & Cachet agent');

    // Note bas
    $pdf->Ln(8);
    $pdf->SetFont('Arial','I',8);
    $pdf->setColor(IllicoPDF::C_GRIS);
    $pdf->MultiCell(0, 4, 'Ce reçu constitue la preuve officielle du dépôt effectué. Conservez-le précieusement. En cas de réclamation, veuillez vous présenter à votre succursale avec ce document.', 0, 'C');

    $pdf->Output('D', 'recu_depot_'.$id_compte.'_'.date('Ymd_His').'.pdf');
    exit;
}


// ════════════════════════════════════════════════════════════════
// PDF REÇU RETRAIT
// ════════════════════════════════════════════════════════════════
function genRetrait(PDO $pdo, string $id_compte): void
{
    $stmt = $pdo->prepare("
        SELECT t.*, c.id_compte, c.devise,
               CONCAT(cl.prenom,' ',cl.nom) AS titulaire,
               cl.id_client, cl.telephone,
               tc.nom AS type_compte,
               u.nom_complet AS operateur,
               s.nom AS succursale, s.code AS succ_code,
               s.telephone AS succ_tel, s.email AS succ_email
        FROM transactions t
        JOIN comptes c  ON t.compte_id = c.id
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN utilisateurs u   ON t.utilisateur_id = u.id
        JOIN succursales s    ON t.succursale_id = s.id
        WHERE c.id_compte = ? AND t.type = 'retrait'
        ORDER BY t.date_transaction DESC LIMIT 1
    ");
    $stmt->execute([$id_compte]);
    $d = $stmt->fetch();
    if (!$d) { http_response_code(404); die('Transaction introuvable'); }

    $pdf = new IllicoPDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(IllicoPDF::M_L, 45, IllicoPDF::M_R);
    $pdf->SetAutoPageBreak(true, 25);

    $pdf->bandeHeader('RECU DE RETRAIT', 'Succursale : '.$d['succ_code'].' — '.$d['succursale'], false);
    $pdf->refBox(txId((int)$d['id']));
    $pdf->montantBox('Montant retiré', '- '.fm((float)$d['montant'], $d['devise']), IllicoPDF::C_ROUGE);

    $pdf->sectionTitle('INFORMATIONS DE LA TRANSACTION');
    $rows = [
        ['Date & heure',    fd($d['date_transaction'], true)],
        ['N° de compte',    $d['id_compte']],
        ['NIF / CINU',      $d['id_client']],
        ['Titulaire',       $d['titulaire']],
        ['Type de compte',  $d['type_compte']],
        ['Téléphone',       $d['telephone'] ?: '—'],
        ['Description',     $d['description'] ?: 'Retrait en espèces'],
    ];
    foreach ($rows as $i => $row)
        $pdf->dataRow($row[0], $row[1], false, IllicoPDF::C_TEXTE, $i%2===0);

    $pdf->sectionTitle('RÉCAPITULATIF DU SOLDE');
    $pdf->dataRow('Solde avant retrait', fm((float)$d['solde_avant'],  $d['devise']), false, IllicoPDF::C_TEXTE, true);
    $pdf->dataRow('Montant retiré',      '- '.fm((float)$d['montant'], $d['devise']), true,  IllicoPDF::C_ROUGE, false);

    $pdf->SetLineWidth(0.6);
    $pdf->setDraw(IllicoPDF::C_ROUGE);
    $pdf->Line(IllicoPDF::M_L, $pdf->GetY(), 210-IllicoPDF::M_R, $pdf->GetY());
    $pdf->SetLineWidth(0.2);
    $y0 = $pdf->GetY()+1;
    $pdf->SetFillColor(254,242,242);
    $pdf->Rect(IllicoPDF::M_L, $y0, IllicoPDF::W, 9, 'F');
    $pdf->SetFont('Arial','B',11);
    $pdf->setColor(IllicoPDF::C_ROUGE);
    $pdf->SetXY(IllicoPDF::M_L, $y0);
    $pdf->Cell(55, 9, 'NOUVEAU SOLDE :', 0, 0, 'L');
    $pdf->Cell(0,  9, fm((float)$d['solde_apres'], $d['devise']), 0, 1, 'R');

    $pdf->Ln(3);
    $pdf->sectionTitle('INFORMATIONS DE L\'AGENT');
    $pdf->dataRow('Opérateur',  $d['operateur'], false, IllicoPDF::C_TEXTE, true);
    $pdf->dataRow('Succursale', $d['succ_code'].' — '.$d['succursale'], false, IllicoPDF::C_TEXTE, false);
    if ($d['succ_tel'])   $pdf->dataRow('Téléphone agence', $d['succ_tel'],   false, IllicoPDF::C_TEXTE, true);
    if ($d['succ_email']) $pdf->dataRow('Email agence',     $d['succ_email'], false, IllicoPDF::C_TEXTE, false);

    $pdf->signaturesBlock('Signature du bénéficiaire', 'Signature & Cachet agent');

    $pdf->Ln(8);
    $pdf->SetFont('Arial','I',8);
    $pdf->setColor(IllicoPDF::C_GRIS);
    $pdf->MultiCell(0, 4, 'Ce reçu constitue la preuve officielle du retrait effectué. En cas de litige, veuillez contacter votre succursale avec ce document dans les 48 heures.', 0, 'C');

    $pdf->Output('D', 'recu_retrait_'.$id_compte.'_'.date('Ymd_His').'.pdf');
    exit;
}


// ════════════════════════════════════════════════════════════════
// PDF FICHE CLIENT (avec espace photo + infos personnelles)
// ════════════════════════════════════════════════════════════════
function genFicheClient(PDO $pdo, string $id_compte, string $id_client_recherche=''): void
{
    // Récupérer le compte et le titulaire
    $stmt = $pdo->prepare("
        SELECT c.*, tc.nom AS type_compte, tc.taux_interet, tc.solde_minimum,
               s.code AS succ_code, s.nom AS succursale_nom,
               cl.id AS client_pk, cl.id_client, cl.nom, cl.prenom, cl.telephone,
               cl.email, cl.adresse, cl.photo, cl.date_naissance, cl.lieu_naissance,
               cl.type_piece, cl.created_at
        FROM comptes c
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN succursales s    ON c.succursale_id   = s.id
        JOIN clients cl       ON c.titulaire_principal_id = cl.id
        WHERE c.id_compte = ?
    ");
    $stmt->execute([$id_compte]);
    $compte = $stmt->fetch();
    if (!$compte) { http_response_code(404); die('Compte introuvable'); }

    // Déterminer le client à afficher
    $client     = $compte;
    $statutRole = 'Titulaire principal';
    $isCotit    = false;

    if (!empty($id_client_recherche) && $id_client_recherche !== $compte['id_client']) {
        $s2 = $pdo->prepare("SELECT * FROM clients WHERE id_client = ?");
        $s2->execute([$id_client_recherche]);
        $cl2 = $s2->fetch();
        if ($cl2) {
            $s3 = $pdo->prepare("SELECT COUNT(*) FROM compte_cotitulaires WHERE compte_id=? AND client_id=?");
            $s3->execute([$compte['id'], $cl2['id']]);
            if ((int)$s3->fetchColumn() > 0) {
                $client     = $cl2;
                $statutRole = 'Co-titulaire';
                $isCotit    = true;
            }
        }
    }

    // Co-titulaires
    $stCot = $pdo->prepare("SELECT cl.* FROM clients cl JOIN compte_cotitulaires cc ON cl.id=cc.client_id WHERE cc.compte_id=?");
    $stCot->execute([$compte['id']]);
    $cotitulaires = $stCot->fetchAll();

    // Titulaire principal si affichage co-titulaire
    $titulairePrincipal = null;
    if ($isCotit) {
        $sTP = $pdo->prepare("SELECT * FROM clients WHERE id=?");
        $sTP->execute([$compte['titulaire_principal_id']]);
        $titulairePrincipal = $sTP->fetch();
    }

    $pdf = new IllicoPDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(IllicoPDF::M_L, 45, IllicoPDF::M_R);
    $pdf->SetAutoPageBreak(true, 25);

    // En-tête
    $pdf->bandeHeader('FICHE D\'IDENTIFICATION CLIENT',
        'Compte N° '.$compte['id_compte'].' — '.$compte['succ_code'].' '.$compte['succursale_nom']);

    // Badge rôle
    $roleColor = $isCotit ? [147,51,234] : IllicoPDF::C_BLEU;
    $pdf->SetX(IllicoPDF::M_L);
    $pdf->badge($statutRole, $roleColor);
    $pdf->Ln(10);

    // Bloc photo + identité
    $xPhoto = IllicoPDF::M_L;
    $yPhoto = $pdf->GetY();
    $wPhoto = 60; $hPhoto = 50;

    // Bordure cadre photo
    $pdf->SetLineWidth(0.5);
    $pdf->setDraw(IllicoPDF::C_BLEU);
    $pdf->Rect($xPhoto, $yPhoto, $wPhoto, $hPhoto);

    // Photo si disponible
    $photoPath = null;
    if (!empty($client['photo'])) {
        $tryPaths = [
            __DIR__.'/uploads/photos/'.basename($client['photo']),
            __DIR__.'/'.ltrim($client['photo'],'/'),
            __DIR__.'/../'.ltrim($client['photo'],'./'),
        ];
        foreach ($tryPaths as $p) {
            if (file_exists($p) && in_array(strtolower(pathinfo($p,PATHINFO_EXTENSION)),['jpg','jpeg','png'])) {
                $photoPath = $p; break;
            }
        }
    }
    if ($photoPath) {
        $ext = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
        $fpdfType = ($ext === 'png') ? 'PNG' : 'JPEG';
        $pdf->Image($photoPath, $xPhoto+0.5, $yPhoto+0.5, $wPhoto-1, $hPhoto-1, $fpdfType);
    } else {
        // Placeholder texte
        $pdf->SetFont('Arial','',7.5);
        $pdf->setColor(IllicoPDF::C_GRIS);
        $pdf->SetXY($xPhoto, $yPhoto + $hPhoto/2 - 5);
        $pdf->Cell($wPhoto, 5, 'PHOTO', 0, 1, 'C');
        $pdf->SetXY($xPhoto, $yPhoto + $hPhoto/2);
        $pdf->Cell($wPhoto, 5, "D'IDENTITE", 0, 1, 'C');
    }
    $pdf->SetFont('Arial','',7);
    $pdf->setColor(IllicoPDF::C_GRIS);
    $pdf->SetXY($xPhoto, $yPhoto+$hPhoto+1);
    $pdf->Cell($wPhoto, 4, "Photo d'identité", 0, 1, 'C');

    // Infos identité à droite de la photo
    $xInfo = $xPhoto + $wPhoto + 6;
    $wInfo = IllicoPDF::W - $wPhoto - 6;
    $yInfo = $yPhoto;

    $pdf->SetFont('Arial','B',14);
    $pdf->setColor(IllicoPDF::C_BLEU);
    $pdf->SetXY($xInfo, $yInfo);
    $pdf->Cell($wInfo, 8, strtoupper($client['nom']).' '.$client['prenom'], 0, 1, 'L');

    $pdf->SetFont('Arial','',10);
    $pdf->setColor(IllicoPDF::C_GRIS);
    $pdf->SetXY($xInfo, $yInfo+9);
    $pdf->Cell($wInfo, 5, ($client['type_piece']??'NIF').'/CINU : '.$client['id_client'], 0, 1, 'L');

    // Mini-grille infos rapides
    $quickInfos = [
        ['Date naissance', fd($client['date_naissance'] ?? null)],
        ['Lieu naissance', $client['lieu_naissance'] ?? '—'],
        ['Téléphone',      $client['telephone'] ?? '—'],
        ['Email',          $client['email']   ?? '—'],
    ];
    $yq = $yInfo + 16;
    foreach ($quickInfos as $qi) {
        $pdf->SetFont('Arial','B',8.5);
        $pdf->setColor(IllicoPDF::C_GRIS);
        $pdf->SetXY($xInfo, $yq);
        $pdf->Cell(35, 6, $qi[0].':',0,0,'L');
        $pdf->SetFont('Arial','',8.5);
        $pdf->setColor(IllicoPDF::C_TEXTE);
        $pdf->Cell($wInfo-35, 6, $qi[1],0,1,'L');
        $yq += 6;
    }

    $pdf->SetXY(IllicoPDF::M_L, $yPhoto + $hPhoto + 8);

    // Adresse
    $pdf->sectionTitle('COORDONNÉES');
    $pdf->dataRow('Adresse complète', $client['adresse'] ?? '—', false, IllicoPDF::C_TEXTE, true);
    $pdf->dataRow("Date d'inscription", fd($client['created_at'] ?? null), false, IllicoPDF::C_TEXTE, false);

    // Titulaire principal (si co-titulaire)
    if ($isCotit && $titulairePrincipal) {
        $pdf->sectionTitle('TITULAIRE PRINCIPAL DU COMPTE');
        $pdf->dataRow('Nom complet', $titulairePrincipal['prenom'].' '.$titulairePrincipal['nom'], false, IllicoPDF::C_TEXTE, true);
        $pdf->dataRow('NIF/CINU',   $titulairePrincipal['id_client'], false, IllicoPDF::C_TEXTE, false);
        $pdf->dataRow('Téléphone',  $titulairePrincipal['telephone'] ?? '—', false, IllicoPDF::C_TEXTE, true);
    }

    // Co-titulaires
    if (!empty($cotitulaires)) {
        $autres = array_filter($cotitulaires, fn($c) => $c['id'] != $client['id']);
        if (!empty($autres)) {
            $pdf->sectionTitle('CO-TITULAIRES ('.count($autres).')');
            foreach (array_values($autres) as $i => $cot) {
                $pdf->dataRow(
                    $cot['prenom'].' '.$cot['nom'],
                    $cot['id_client'].($cot['telephone']?' — '.$cot['telephone']:''),
                    false, IllicoPDF::C_BLEU2, $i%2===0
                );
            }
        }
    }

    $pdf->signaturesBlock('Signature du client', 'Signature & Cachet agent');

    $pdf->Output('D', 'fiche_client_'.$id_compte.'_'.date('Ymd_His').'.pdf');
    exit;
}


// ════════════════════════════════════════════════════════════════
// PDF FICHE COMPTE (sans photo, avec solde + transactions)
// ════════════════════════════════════════════════════════════════
function genFicheCompte(PDO $pdo, string $id_compte): void
{
    $stmt = $pdo->prepare("
        SELECT c.*, tc.nom AS type_compte, tc.taux_interet, tc.solde_minimum,
               s.code AS succ_code, s.nom AS succursale_nom, s.telephone AS succ_tel,
               s.email AS succ_email,
               cl.id_client, cl.nom, cl.prenom, cl.telephone, cl.email, cl.adresse
        FROM comptes c
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN succursales s    ON c.succursale_id   = s.id
        JOIN clients cl       ON c.titulaire_principal_id = cl.id
        WHERE c.id_compte = ?
    ");
    $stmt->execute([$id_compte]);
    $compte = $stmt->fetch();
    if (!$compte) { http_response_code(404); die('Compte introuvable'); }

    $stCot = $pdo->prepare("SELECT cl.* FROM clients cl JOIN compte_cotitulaires cc ON cl.id=cc.client_id WHERE cc.compte_id=?");
    $stCot->execute([$compte['id']]);
    $cotitulaires = $stCot->fetchAll();

    $stTx = $pdo->prepare("
        SELECT t.*, u.nom_complet AS operateur
        FROM transactions t JOIN utilisateurs u ON t.utilisateur_id=u.id
        WHERE t.compte_id=? ORDER BY t.date_transaction DESC LIMIT 10
    ");
    $stTx->execute([$compte['id']]);
    $transactions = $stTx->fetchAll();

    $pdf = new IllicoPDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(IllicoPDF::M_L, 45, IllicoPDF::M_R);
    $pdf->SetAutoPageBreak(true, 25);

    $pdf->bandeHeader('RELEVE DE COMPTE',
        'Compte N° '.$compte['id_compte'].' — '.$compte['succ_code'].' '.$compte['succursale_nom']);

    // Solde en évidence
    $solde   = (float)$compte['solde'];
    $couleur = $solde >= 0 ? IllicoPDF::C_VERT : IllicoPDF::C_ROUGE;
    $pdf->montantBox('Solde actuel', fm($solde, $compte['devise']), $couleur);

    // Informations du compte
    $pdf->sectionTitle('INFORMATIONS DU COMPTE');
    $compteInfos = [
        ['N° de compte',      $compte['id_compte']],
        ['Type de compte',    $compte['type_compte']],
        ['Devise',            $compte['devise']],
        ['Date d\'ouverture', fd($compte['date_creation'])],
        ['Taux d\'intérêt',   $compte['taux_interet'].'%'],
        ['Solde minimum',     fm((float)$compte['solde_minimum'], $compte['devise'])],
        ['Statut',            $compte['statut'] === 'actif' ? 'Actif' : 'Bloqué'],
        ['Succursale',        $compte['succ_code'].' — '.$compte['succursale_nom']],
    ];
    foreach ($compteInfos as $i => $row)
        $pdf->dataRow($row[0], $row[1], false, IllicoPDF::C_TEXTE, $i%2===0);

    // Titulaire principal
    $pdf->sectionTitle('TITULAIRE PRINCIPAL');
    $clientInfos = [
        ['NIF / CINU',  $compte['id_client']],
        ['Nom complet', $compte['prenom'].' '.$compte['nom']],
        ['Téléphone',   $compte['telephone'] ?? '—'],
        ['Email',       $compte['email']  ?? '—'],
        ['Adresse',     $compte['adresse']?? '—'],
    ];
    foreach ($clientInfos as $i => $row)
        $pdf->dataRow($row[0], $row[1], false, IllicoPDF::C_TEXTE, $i%2===0);

    // Co-titulaires
    if (!empty($cotitulaires)) {
        $pdf->sectionTitle('CO-TITULAIRES ('.count($cotitulaires).')');
        foreach ($cotitulaires as $i => $cot) {
            $pdf->dataRow(
                $cot['prenom'].' '.$cot['nom'],
                $cot['id_client'].($cot['telephone'] ? ' — '.$cot['telephone'] : ''),
                false, IllicoPDF::C_BLEU2, $i%2===0
            );
        }
    }

    // 3 dernières transactions
 if (!empty($transactions)) {

    // LIMITE À 3 OPÉRATIONS
    $transactions = array_slice($transactions, 0, 3);

    $pdf->sectionTitle('3 DERNIERES OPERATIONS');

    // En-tête tableau
    $colW = [30, 22, 35, 35, 0];
    $cols = ['Date', 'Type', 'Montant', 'Solde après', 'Opérateur'];

    $pdf->SetFont('Arial','B',8.5);
    $pdf->setColor(IllicoPDF::C_BLEU);
    $pdf->SetFillColor(219,234,254);
    $xc = IllicoPDF::M_L;
    $pdf->SetX($xc);
    foreach ($cols as $ci => $col) {
        $w = $ci < 4 ? $colW[$ci] : (IllicoPDF::W - array_sum(array_slice($colW,0,4)));
        $pdf->Cell($w, 7, $col, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Données
    $pdf->SetFont('Arial','',8.5);
    foreach ($transactions as $i => $tx) {
        $isDepot = $tx['type'] === 'depot';
        $fill = $i%2===0;
        if ($fill) $pdf->SetFillColor(248,250,252);

        $montantStr = ($isDepot ? '+ ' : '- ').fm((float)$tx['montant'], $compte['devise']);
        $row = [
            fd($tx['date_transaction'], true),
            ucfirst($tx['type']),
            $montantStr,
            fm((float)$tx['solde_apres'], $compte['devise']),
            $tx['operateur'],
        ];

        $xc = IllicoPDF::M_L;
        $pdf->SetX($xc);
        foreach ($row as $ci => $val) {
            $w = $ci < 4 ? $colW[$ci] : (IllicoPDF::W - array_sum(array_slice($colW,0,4)));
            if ($ci === 2) {
                $isDepot ? $pdf->setColor(IllicoPDF::C_VERT) : $pdf->setColor(IllicoPDF::C_ROUGE);
            } else {
                $pdf->setColor(IllicoPDF::C_TEXTE);
            }
            $pdf->Cell($w, 6, $val, 'LR', 0, $ci===0?'L':'R', $fill);
        }
        $pdf->Ln();
    }

    // Fermeture tableau
    $pdf->SetX(IllicoPDF::M_L);
    $pdf->Cell(IllicoPDF::W, 0, '', 'T');
    $pdf->Ln(3);

} else {
    $pdf->SetFont('Arial','I',9);
    $pdf->setColor(IllicoPDF::C_GRIS);
    $pdf->Cell(0, 7, 'Aucune opération enregistrée.', 0, 1, 'C');
}

$pdf->signaturesBlock('Signature du titulaire', 'Signature & Cachet agent');

$pdf->Output('D', 'releve_compte_'.$id_compte.'_'.date('Ymd_His').'.pdf');
exit;
}

// ════════════════════════════════════════════════════════════════
// PDF FICHE CLIENT SANS COMPTE
// ════════════════════════════════════════════════════════════════
function genFicheClientSeul(PDO $pdo, string $id_client): void
{
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id_client = ?");
    $stmt->execute([$id_client]);
    $client = $stmt->fetch();
    if (!$client) { http_response_code(404); die('Client introuvable'); }

    $pdf = new IllicoPDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(IllicoPDF::M_L, 45, IllicoPDF::M_R);
    $pdf->SetAutoPageBreak(true, 25);

    $pdf->bandeHeader('FICHE CLIENT', 'Enregistré le '.fd($client['created_at']).' — Sans compte bancaire');

    // Badge "sans compte"
    $pdf->SetX(IllicoPDF::M_L);
    $pdf->badge('Client sans compte bancaire', [245,158,11]);
    $pdf->Ln(10);

    // Bloc photo + identité
    $xPhoto = IllicoPDF::M_L;
    $yPhoto = $pdf->GetY();
    $wPhoto = 60; $hPhoto = 50;

    $pdf->SetLineWidth(0.5);
    $pdf->setDraw(IllicoPDF::C_BLEU);
    $pdf->Rect($xPhoto, $yPhoto, $wPhoto, $hPhoto);

    $photoPath = null;
    if (!empty($client['photo'])) {
        $tryPaths = [
            __DIR__.'/uploads/photos/'.basename($client['photo']),
            __DIR__.'/'.ltrim($client['photo'],'/'),
            __DIR__.'/../'.ltrim($client['photo'],'./'),
        ];
        foreach ($tryPaths as $p) {
            if (file_exists($p) && in_array(strtolower(pathinfo($p,PATHINFO_EXTENSION)),['jpg','jpeg','png'])) {
                $photoPath = $p; break;
            }
        }
    }
    if ($photoPath) {
        $ext = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
        $pdf->Image($photoPath, $xPhoto+0.5, $yPhoto+0.5, $wPhoto-1, $hPhoto-1, $ext==='png'?'PNG':'JPEG');
    } else {
        $pdf->SetFont('Arial','',7.5);
        $pdf->setColor(IllicoPDF::C_GRIS);
        $pdf->SetXY($xPhoto, $yPhoto+$hPhoto/2-5);
        $pdf->Cell($wPhoto,5,'PHOTO',0,1,'C');
        $pdf->SetXY($xPhoto,$yPhoto+$hPhoto/2);
        $pdf->Cell($wPhoto,5,"D'IDENTITE",0,1,'C');
    }
    $pdf->SetFont('Arial','',7);
    $pdf->setColor(IllicoPDF::C_GRIS);
    $pdf->SetXY($xPhoto,$yPhoto+$hPhoto+1);
    $pdf->Cell($wPhoto,4,"Photo d'identite",0,1,'C');

    $xInfo = $xPhoto+$wPhoto+6;
    $wInfo = IllicoPDF::W-$wPhoto-6;
    $yInfo = $yPhoto;

    $pdf->SetFont('Arial','B',14);
    $pdf->setColor(IllicoPDF::C_BLEU);
    $pdf->SetXY($xInfo,$yInfo);
    $pdf->Cell($wInfo,8,strtoupper($client['nom']).' '.$client['prenom'],0,1,'L');

    $pdf->SetFont('Arial','',10);
    $pdf->setColor(IllicoPDF::C_GRIS);
    $pdf->SetXY($xInfo,$yInfo+9);
    $pdf->Cell($wInfo,5,($client['type_piece']??'NIF').'/CINU : '.$client['id_client'],0,1,'L');

    $quickInfos = [
        ['Date naissance', fd($client['date_naissance'] ?? null)],
        ['Lieu naissance', $client['lieu_naissance'] ?? '—'],
        ['Téléphone',      $client['telephone'] ?? '—'],
        ['Email',          $client['email']   ?? '—'],
    ];
    $yq = $yInfo+16;
    foreach ($quickInfos as $qi) {
        $pdf->SetFont('Arial','B',8.5);
        $pdf->setColor(IllicoPDF::C_GRIS);
        $pdf->SetXY($xInfo,$yq);
        $pdf->Cell(35,6,$qi[0].':',0,0,'L');
        $pdf->SetFont('Arial','',8.5);
        $pdf->setColor(IllicoPDF::C_TEXTE);
        $pdf->Cell($wInfo-35,6,$qi[1],0,1,'L');
        $yq += 6;
    }

    $pdf->SetXY(IllicoPDF::M_L, $yPhoto+$hPhoto+8);

    $pdf->sectionTitle('COORDONNÉES COMPLÈTES');
    $infos = [
        ['Adresse',          $client['adresse']      ?? '—'],
        ['Lieu de naissance',$client['lieu_naissance']?? '—'],
        ['Date naissance',   fd($client['date_naissance']?? null)],
        ["Date d'inscription", fd($client['created_at']   ?? null)],
    ];
    foreach ($infos as $i => $row)
        $pdf->dataRow($row[0], $row[1], false, IllicoPDF::C_TEXTE, $i%2===0);

    $pdf->signaturesBlock('Signature du client', 'Signature & Cachet agent');

    $pdf->Output('D', 'fiche_client_'.str_replace('-','_',$id_client).'_'.date('Ymd_His').'.pdf');
    exit;
}


// ════════════════════════════════════════════════════════════════
// DISPATCH
// ════════════════════════════════════════════════════════════════
switch ($action) {
    case 'depot':
        if (!$id_compte) die('Paramètre id_compte manquant');
        genDepot($pdo, $id_compte);
        break;

    case 'retrait':
        if (!$id_compte) die('Paramètre id_compte manquant');
        genRetrait($pdo, $id_compte);
        break;

    case 'fiche_client':
        if (!$id_compte) die('Paramètre id_compte manquant');
        genFicheClient($pdo, $id_compte, $id_client_recherche);
        break;

    case 'fiche_compte':
        if (!$id_compte) die('Paramètre id_compte manquant');
        genFicheCompte($pdo, $id_compte);
        break;

    case 'fiche_client_seul':
        if (!$id_client) die('Paramètre id_client manquant');
        genFicheClientSeul($pdo, $id_client);
        break;

    // ════════════════════════════════════════════════════════════════
// PDF RAPPORT (NOUVEAU)
// ════════════════════════════════════════════════════════════════
case 'rapport':
    $periode = $_GET['periode'] ?? 'jour';
    $date_debut = $_GET['date_debut'] ?? date('Y-m-d');
    $date_fin = $_GET['date_fin'] ?? date('Y-m-d');
    
    // Titre selon période
    switch ($periode) {
        case 'jour': $titre = "Rapport journalier - " . date('d/m/Y', strtotime($date_debut)); break;
        case 'semaine': $titre = "Rapport hebdomadaire"; break;
        case 'mois': $titre = "Rapport mensuel - " . date('F Y', strtotime($date_debut)); break;
        case 'annee': $titre = "Rapport annuel - " . date('Y', strtotime($date_debut)); break;
        default: $titre = "Rapport personnalisé";
    }
    
    // Récupération des données
    $tx = $pdo->prepare("
        SELECT COUNT(*) as nb, COALESCE(SUM(CASE WHEN type='depot' THEN montant ELSE 0 END),0) as depots,
               COALESCE(SUM(CASE WHEN type='retrait' THEN montant ELSE 0 END),0) as retraits,
               COALESCE(SUM(montant),0) as volume
        FROM transactions WHERE DATE(date_transaction) BETWEEN ? AND ?
    ");
    $tx->execute([$date_debut, $date_fin]);
    $data = $tx->fetch();
    
    // Totaux généraux
    $total_clients = $pdo->query("SELECT COUNT(*) as total FROM clients")->fetch()['total'];
    $total_personnel = $pdo->query("SELECT COUNT(*) as total FROM utilisateurs WHERE actif = 1")->fetch()['total'];
    $total_comptes = $pdo->query("SELECT COUNT(*) as total FROM comptes WHERE statut = 'actif'")->fetch()['total'];
    $total_depots_global = $pdo->query("SELECT COALESCE(SUM(solde), 0) as total FROM comptes WHERE statut = 'actif'")->fetch()['total'];
    
    // Transactions
    $transactions = $pdo->prepare("
        SELECT t.*, c.id_compte, c.devise, CONCAT(cl.nom,' ',cl.prenom) as client, u.username
        FROM transactions t
        JOIN comptes c ON t.compte_id=c.id
        JOIN clients cl ON c.titulaire_principal_id=cl.id
        JOIN utilisateurs u ON t.utilisateur_id=u.id
        WHERE DATE(t.date_transaction) BETWEEN ? AND ?
        ORDER BY t.date_transaction DESC LIMIT 50
    ");
    $transactions->execute([$date_debut, $date_fin]);
    
    $pdf = new IllicoPDF('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(IllicoPDF::M_L, 45, IllicoPDF::M_R);
    $pdf->SetAutoPageBreak(true, 25);
    
    $pdf->bandeHeader('RAPPORT D\'ACTIVITE', $titre);
    
    // Résumé
    $pdf->sectionTitle('RESUME DE LA PERIODE');
    $pdf->dataRow('Nombre de transactions', number_format($data['nb']), false, IllicoPDF::C_TEXTE, true);
    $pdf->dataRow('Volume total', number_format($data['volume'], 0, ',', ' ') . ' HTG', false, IllicoPDF::C_TEXTE, false);
    $pdf->dataRow('Total dépôts', number_format($data['depots'], 0, ',', ' ') . ' HTG', false, IllicoPDF::C_VERT, true);
    $pdf->dataRow('Total retraits', number_format($data['retraits'], 0, ',', ' ') . ' HTG', false, IllicoPDF::C_ROUGE, false);
    
    $pdf->sectionTitle('TOTAUX GENERAUX');
    $pdf->dataRow('Total clients', number_format($total_clients), false, IllicoPDF::C_TEXTE, true);
    $pdf->dataRow('Personnel actif', number_format($total_personnel), false, IllicoPDF::C_TEXTE, false);
    $pdf->dataRow('Comptes actifs', number_format($total_comptes), false, IllicoPDF::C_TEXTE, true);
    $pdf->dataRow('Total dépôts (tous comptes)', number_format($total_depots_global, 0, ',', ' ') . ' HTG', false, IllicoPDF::C_VERT, false);
    
    // Liste des transactions
    $pdf->sectionTitle('DETAIL DES TRANSACTIONS');
    
    if ($transactions->rowCount() > 0) {
        $pdf->SetFont('Arial','B',8);
        $pdf->setColor(IllicoPDF::C_BLEU);
        $pdf->SetFillColor(219,234,254);
        $headers = ['Date', 'Compte', 'Client', 'Type', 'Montant'];
        $colW = [35, 25, 45, 25, 50];
        $xc = IllicoPDF::M_L;
        foreach ($headers as $i => $h) {
            $pdf->Cell($colW[$i], 7, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        $pdf->SetFont('Arial','',7.5);
        while ($t = $transactions->fetch()) {
            $fill = ($transactions->rowCount() % 2 == 0);
            if ($fill) $pdf->SetFillColor(248,250,252);
            
            $pdf->SetX(IllicoPDF::M_L);
            $pdf->Cell($colW[0], 6, date('d/m/Y H:i', strtotime($t['date_transaction'])), 'LR', 0, 'L', $fill);
            $pdf->Cell($colW[1], 6, $t['id_compte'], 'LR', 0, 'L', $fill);
            $pdf->Cell($colW[2], 6, ct(substr($t['client'], 0, 20)), 'LR', 0, 'L', $fill);
            
            $typeColor = $t['type'] == 'depot' ? IllicoPDF::C_VERT : IllicoPDF::C_ROUGE;
            $pdf->setColor($typeColor);
            $pdf->Cell($colW[3], 6, ucfirst($t['type']), 'LR', 0, 'L', $fill);
            
            $pdf->setColor(IllicoPDF::C_TEXTE);
            $pdf->Cell($colW[4], 6, number_format($t['montant'], 2, ',', ' ') . ' ' . $t['devise'], 'LR', 1, 'R', $fill);
        }
        $pdf->SetX(IllicoPDF::M_L);
        $pdf->Cell(array_sum($colW), 0, '', 'T');
    } else {
        $pdf->SetFont('Arial','I',9);
        $pdf->setColor(IllicoPDF::C_GRIS);
        $pdf->Cell(0, 7, 'Aucune transaction sur cette période', 0, 1, 'C');
    }
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial','I',8);
    $pdf->setColor(IllicoPDF::C_GRIS);
    $pdf->Cell(0, 5, 'Rapport généré le ' . date('d/m/Y à H:i') . ' par ' . $_SESSION['nom_complet'], 0, 1, 'R');
    
    $pdf->Output('D', 'rapport_' . $periode . '_' . date('Ymd_His') . '.pdf');
    exit;

    default:
        http_response_code(400);
        echo '<h2>S&P illico — Générateur PDF</h2>';
        echo '<p>Actions disponibles :</p><ul>';
        echo '<li>?action=depot&amp;id_compte=XXXXX</li>';
        echo '<li>?action=retrait&amp;id_compte=XXXXX</li>';
        echo '<li>?action=fiche_client&amp;id_compte=XXXXX[&amp;id_client_recherche=XXX-XXX-XXX-X]</li>';
        echo '<li>?action=fiche_compte&amp;id_compte=XXXXX</li>';
        echo '<li>?action=fiche_client_seul&amp;id_client=XXX-XXX-XXX-X</li>';
        echo '</ul>';
}
?>