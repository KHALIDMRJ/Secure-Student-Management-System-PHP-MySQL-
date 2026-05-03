<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/auth.php';

configure_session();
session_start();
prevent_session_fixation();
validate_session_integrity();
require_auth();

$pageTitle  = 'Saisir les notes';
$breadcrumb = 'Saisir les notes';
$pdo        = getPDO();

/**
 * Normalise a posted note string.
 * Accepts: '', '12', '12.5', '12,5'.
 *
 * @param string $raw The raw form value.
 * @return float|null|false  null = empty input, false = invalid, float = OK.
 */
function normalize_note(string $raw) {
    $raw = trim($raw);
    if ($raw === '') return null;
    $raw = str_replace(',', '.', $raw);
    if (!is_numeric($raw)) return false;
    $f = (float)$raw;
    if ($f < 0 || $f > 20) return false;
    return $f;
}

/**
 * Derive the inscription statut from a note value.
 *
 * @param float|null $note
 * @return 'inscrit'|'valide'|'echoue'
 */
function statut_for_note(?float $note): string {
    if ($note === null) return 'inscrit';
    return $note >= 10 ? 'valide' : 'echoue';
}

// ── Resolve student ID ──────────────────────────────────────────────────────
$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT,
                          ['options' => ['min_range' => 1]]);
if ($studentId === false || $studentId === null) {
    $studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT,
                              ['options' => ['min_range' => 1]]);
}
if ($studentId === false || $studentId === null) {
    redirect('?page=notes');
}

// ── Load the student ────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT id, nom, prenom, email, filieres, is_active
         FROM etudiants WHERE id = :id LIMIT 1'
    );
    $stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('notes_etudiant load: ' . $e->getMessage());
    abort(500);
}
if (!$student) {
    redirect('?page=notes');
}

// ── Save handler (POST) ────────────────────────────────────────────────────
$saveErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (rate_limit_exceeded('save_notes', 20, 60)) {
        abort(429, 'Trop de tentatives. Veuillez patienter quelques minutes.');
    }
    if (!csrf_token_verify()) {
        abort(403, 'Jeton CSRF invalide.');
    }

    $posted = $_POST['notes'] ?? [];
    if (!is_array($posted)) $posted = [];

    // Normalise everything BEFORE writing — if any row is invalid, abort the
    // whole save and re-render with errors. All-or-nothing keeps the data
    // consistent and the UX predictable.
    $valid    = [];           // [inscriptionId => ['note' => float|null, 'statut' => string]]
    foreach ($posted as $iid => $raw) {
        $iid = (int)$iid;
        if ($iid <= 0) continue;
        $note = normalize_note((string)$raw);
        if ($note === false) {
            $saveErrors[] = 'Note invalide pour l\'inscription #' . $iid . ' (entre 0 et 20).';
            continue;
        }
        $valid[$iid] = ['note' => $note, 'statut' => statut_for_note($note)];
    }

    if (empty($saveErrors) && !empty($valid)) {
        try {
            $pdo->beginTransaction();
            $up = $pdo->prepare(
                'UPDATE inscriptions
                    SET note   = :note,
                        statut = :statut
                  WHERE id = :id AND etudiant_id = :sid'
            );
            $changed = 0;
            foreach ($valid as $iid => $d) {
                if ($d['note'] === null) {
                    $up->bindValue(':note', null, PDO::PARAM_NULL);
                } else {
                    $up->bindValue(':note', $d['note']); // PDO casts decimal as string OK
                }
                $up->bindValue(':statut', $d['statut'], PDO::PARAM_STR);
                $up->bindValue(':id',     $iid,         PDO::PARAM_INT);
                // Ownership check inside the WHERE — defends against tampered IDs.
                $up->bindValue(':sid',    $studentId,   PDO::PARAM_INT);
                $up->execute();
                $changed += $up->rowCount();
            }
            $pdo->commit();
            csrf_token_renew();
            // PRG: redirect to GET so refresh doesn't re-submit.
            redirect('?page=notes_etudiant&id=' . $studentId . '&saved=' . $changed);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('save notes: ' . $e->getMessage());
            abort(500);
        }
    }
}

// ── Load inscriptions (always — GET, POST-after-error, or POST-after-PRG) ──
try {
    $stmt = $pdo->prepare(
        'SELECT
            i.id, i.note, i.statut, i.inscribed_at,
            m.id AS module_id, m.code, m.nom AS module_nom,
            m.semestre, m.credits
         FROM inscriptions i
         JOIN modules m ON m.id = i.module_id
         WHERE i.etudiant_id = :sid
         ORDER BY m.semestre, m.code'
    );
    $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
    $stmt->execute();
    $inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('notes_etudiant inscriptions: ' . $e->getMessage());
    abort(500);
}

// Group by semester for display.
$bySemester = [];
foreach ($inscriptions as $r) {
    $bySemester[(int)$r['semestre']][] = $r;
}
ksort($bySemester);

// Aggregate stats for the student header.
$nbTotal   = count($inscriptions);
$nbNotes   = 0;
$nbValide  = 0;
$nbEchoue  = 0;
$sumNote   = 0.0;
foreach ($inscriptions as $r) {
    if ($r['note'] !== null) {
        $nbNotes++;
        $sumNote += (float)$r['note'];
    }
    if ($r['statut'] === 'valide') $nbValide++;
    if ($r['statut'] === 'echoue') $nbEchoue++;
}
$moyenne  = $nbNotes > 0 ? round($sumNote / $nbNotes, 2) : null;
$initials = mb_strtoupper(
    mb_substr((string)$student['nom'],    0, 1) .
    mb_substr((string)$student['prenom'], 0, 1)
);

// PRG flash — number of rows actually updated.
$savedCount = filter_input(INPUT_GET, 'saved', FILTER_VALIDATE_INT) ?: null;

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h2>Saisir les notes</h2>
        <div class="subtitle">
            <a href="?page=notes" class="text-muted text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Tous les étudiants
            </a>
        </div>
    </div>
</div>

<?php if ($savedCount !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?= e((string)$savedCount) ?> ligne<?= $savedCount > 1 ? 's' : '' ?>
        mise<?= $savedCount > 1 ? 's' : '' ?> à jour avec succès.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($saveErrors)): ?>
    <div class="alert alert-danger" role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>
            Aucune note enregistrée — veuillez corriger :
        </strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($saveErrors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Student header card -->
<div class="card notes-student-header mb-3">
    <div class="card-body d-flex align-items-center flex-wrap gap-3">
        <div class="notes-avatar"><?= e($initials) ?></div>
        <div class="flex-grow-1">
            <h3 class="notes-student-name">
                <?= e((string)$student['nom']) ?> <?= e((string)$student['prenom']) ?>
            </h3>
            <div class="text-muted small">
                <?= e((string)$student['email']) ?> ·
                <span class="badge-filiere"><?= e((string)$student['filieres']) ?></span>
            </div>
        </div>
        <div class="notes-summary">
            <div>
                <div class="notes-summary-value"><?= e((string)$nbNotes) ?>/<?= e((string)$nbTotal) ?></div>
                <div class="notes-summary-label">Notés</div>
            </div>
            <div>
                <div class="notes-summary-value text-success"><?= e((string)$nbValide) ?></div>
                <div class="notes-summary-label">Validés</div>
            </div>
            <div>
                <div class="notes-summary-value text-danger"><?= e((string)$nbEchoue) ?></div>
                <div class="notes-summary-label">Échoués</div>
            </div>
            <div>
                <div class="notes-summary-value">
                    <?= $moyenne !== null ? e(number_format($moyenne, 2)) : '—' ?>
                </div>
                <div class="notes-summary-label">Moyenne</div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($inscriptions)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-clipboard"></i></div>
            <h5>Aucune inscription</h5>
            <p>Cet étudiant n'est inscrit à aucun module pour l'instant.</p>
        </div>
    </div>
<?php else: ?>

<form method="post" action="?page=notes_etudiant" id="notesForm" autocomplete="off">
    <?= csrf_token_field() ?>
    <input type="hidden" name="student_id" value="<?= e((string)$studentId) ?>">

    <?php foreach ($bySemester as $semNum => $list): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-calendar3 me-2 text-primary"></i>Semestre <?= e((string)$semNum) ?>
                </h5>
                <span class="text-muted small ms-auto"><?= count($list) ?> module(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 notes-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Module</th>
                            <th class="text-center">Crédits</th>
                            <th class="text-center" style="width:140px">Note (/ 20)</th>
                            <th class="text-center" style="width:120px">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $r):
                            $note = $r['note'] !== null ? number_format((float)$r['note'], 2) : '';
                            $stat = (string)$r['statut'];
                        ?>
                            <tr>
                                <td><code class="module-code"><?= e((string)$r['code']) ?></code></td>
                                <td><strong><?= e((string)$r['module_nom']) ?></strong></td>
                                <td class="text-center">
                                    <span class="credits-badge"><?= e((string)$r['credits']) ?></span>
                                </td>
                                <td class="text-center">
                                    <input type="text"
                                           inputmode="decimal"
                                           class="form-control form-control-sm note-input"
                                           name="notes[<?= e((string)$r['id']) ?>]"
                                           value="<?= e($note) ?>"
                                           data-original="<?= e($note) ?>"
                                           placeholder="—"
                                           aria-label="Note pour <?= e((string)$r['code']) ?>">
                                </td>
                                <td class="text-center">
                                    <span class="badge-statut badge-statut--<?= e($stat) ?> note-statut-preview">
                                        <?= e(ucfirst($stat)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Sticky save bar -->
    <div class="notes-save-bar">
        <div class="notes-save-bar-inner">
            <span class="text-muted small me-auto" id="notesDirtyHint">
                <i class="bi bi-info-circle me-1"></i>
                Modifiez les notes puis enregistrez. Une note vide remet le statut à
                <strong>inscrit</strong>.
            </span>
            <a href="?page=notes" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Retour
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Enregistrer toutes les notes
            </button>
        </div>
    </div>
</form>

<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
