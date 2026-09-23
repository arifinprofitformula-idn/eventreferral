<?php
/**
 * includes/lms_form_builder.php
 * Form builder modular untuk Course CMS Admin dengan Dark Theme UI.
 */

class LmsCourseFormBuilder {
    private array $data;
    private array $options;

    public function __construct(array $data = [], array $options = []) {
        $this->data = $data;
        $this->options = $options;
    }

    public function renderStyles(): string {
        return <<<'CSS'
<style>
  :root {
    --lms-form-bg: #121211;
    --lms-form-surface: #181816;
    --lms-form-surface-elevated: #222220;
    --lms-form-control-bg: #222222;
    --lms-form-control-bg-hover: #2a2a28;
    --lms-form-control-border: rgba(255, 255, 255, 0.12);
    --lms-form-control-border-focus: var(--gold-soft, #f4d27a);
    --lms-form-text: #ffffff;
    --lms-form-text-muted: #a8a29a;
    --lms-form-gold: var(--gold, #d6a536);
    --lms-form-gold-soft: var(--gold-soft, #f4d27a);
    --lms-form-danger: #ef4444;
  }
  .lms-form-card {
    background: var(--lms-form-surface);
    border: 1px solid rgba(214, 165, 54, 0.2);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
    margin-bottom: 24px;
  }
  .lms-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }
  .lms-form-field {
    display: grid;
    gap: 6px;
  }
  .lms-form-field.full {
    grid-column: 1 / -1;
  }
  .lms-form-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--lms-form-text);
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .lms-form-label .req {
    color: var(--lms-form-danger);
  }
  .lms-form-hint {
    font-size: 11.5px;
    color: var(--lms-form-text-muted);
  }
  .lms-dark-input,
  .lms-dark-textarea,
  .lms-dark-select {
    width: 100%;
    min-height: 46px;
    background-color: var(--lms-form-control-bg);
    color: var(--lms-form-text);
    border: 1px solid var(--lms-form-control-border);
    border-radius: 12px;
    padding: 10px 14px;
    font: inherit;
    font-size: 13.5px;
    outline: none;
    transition: background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
  }
  .lms-dark-input:hover,
  .lms-dark-textarea:hover,
  .lms-dark-select:hover {
    background-color: var(--lms-form-control-bg-hover);
    border-color: rgba(255, 255, 255, 0.22);
  }
  .lms-dark-input:focus,
  .lms-dark-textarea:focus,
  .lms-dark-select:focus {
    border-color: var(--lms-form-control-border-focus);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--lms-form-gold) 20%, transparent);
  }
  .lms-dark-select {
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23f4d27a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 14px;
    padding-right: 36px;
    appearance: none;
    -webkit-appearance: none;
  }
  .lms-dark-select option {
    background-color: #1e1e1c;
    color: #ffffff;
    padding: 10px;
  }
  .lms-dark-select option:hover,
  .lms-dark-select option:focus,
  .lms-dark-select option:checked {
    background-color: #2e2e2a;
    color: var(--lms-form-gold-soft);
  }
  .lms-checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--lms-form-text);
    cursor: pointer;
    padding: 10px 14px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--lms-form-control-border);
    border-radius: 12px;
  }
  .lms-checkbox-label:hover {
    background: rgba(255, 255, 255, 0.06);
  }
  .lms-actions-bar {
    position: sticky;
    bottom: 20px;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    background: rgba(20, 20, 19, 0.95);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(214, 165, 54, 0.25);
    border-radius: 16px;
    padding: 14px 20px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
  }
  .lms-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 20px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 750;
    cursor: pointer;
    border: 1px solid transparent;
    text-decoration: none;
    transition: transform 160ms ease, background-color 160ms ease;
  }
  .lms-btn:hover {
    transform: translateY(-1px);
  }
  .lms-btn-publish {
    background: linear-gradient(135deg, var(--lms-form-gold), var(--lms-form-gold-soft));
    color: #111110;
  }
  .lms-btn-draft {
    background: rgba(255, 255, 255, 0.08);
    color: var(--lms-form-text);
    border-color: rgba(255, 255, 255, 0.16);
  }
  .lms-btn-draft:hover {
    background: rgba(255, 255, 255, 0.14);
  }
  .lms-btn-cancel {
    background: transparent;
    color: var(--lms-form-text-muted);
    border-color: rgba(255, 255, 255, 0.1);
  }
  .lms-btn-cancel:hover {
    color: var(--lms-form-text);
    background: rgba(255, 255, 255, 0.05);
  }
  .lms-btn-preview {
    background: rgba(214, 165, 54, 0.15);
    color: var(--lms-form-gold-soft);
    border-color: rgba(214, 165, 54, 0.3);
  }
  .lms-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.78);
    backdrop-filter: blur(8px);
    z-index: 100;
    display: none;
    place-items: center;
    padding: 20px;
  }
  .lms-modal {
    width: min(100%, 780px);
    max-height: 90vh;
    overflow-y: auto;
    background: var(--lms-form-surface);
    border: 1px solid var(--lms-form-gold);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6);
  }
  @media (max-width: 768px) {
    .lms-form-grid { grid-template-columns: 1fr; }
    .lms-actions-bar { flex-direction: column; align-items: stretch; }
    .lms-actions-bar > div { width: 100%; display: flex; flex-direction: column; gap: 8px; }
    .lms-btn { width: 100%; }
  }
</style>
CSS;
    }

    private function val(string $key, $default = '') {
        return $this->data[$key] ?? $default;
    }

    private function h($val): string {
        return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    }

    public function renderForm(string $csrfToken, ?int $courseId = null): string {
        $categories = $this->options['categories'] ?? [];
        $instructors = $this->options['instructors'] ?? [];
        $selectedCat = (int)$this->val('category_id', 0);
        $accessType = $this->val('access_type', 'free');
        $level = $this->val('level', 'beginner');
        $status = $this->val('status', 'active');
        $durationHours = (int)floor(((int)$this->val('duration_minutes', 0)) / 60);
        $durationRemainingMinutes = ((int)$this->val('duration_minutes', 0)) % 60;
        $tagsString = $this->val('tags', '');
        $certChecked = !empty($this->val('certificate_enabled')) ? 'checked' : '';
        $isEdit = $courseId !== null && $courseId > 0;
        $previewUrl = $isEdit ? '/course/' . urlencode((string)$this->val('slug', '')) : '#';

        $catOptions = '<option value="">-- Pilih Kategori --</option>';
        foreach ($categories as $cat) {
            $sel = ((int)$cat['id'] === $selectedCat) ? 'selected' : '';
            $catOptions .= '<option value="' . (int)$cat['id'] . '" ' . $sel . '>' . $this->h($cat['name']) . '</option>';
        }

        $levelOptions = '';
        foreach ([
            'beginner' => 'Beginner (Pemula)',
            'intermediate' => 'Intermediate (Menengah)',
            'advanced' => 'Advanced (Tingkat Lanjut)',
        ] as $k => $label) {
            $sel = ($level === $k) ? 'selected' : '';
            $levelOptions .= '<option value="' . $k . '" ' . $sel . '>' . $this->h($label) . '</option>';
        }

        $statusOptions = '';
        foreach ([
            'active' => 'Aktif (Langsung Terbit / Published)',
            'draft' => 'Draft (Nonaktif / Disimpan Sementara)',
            'archived' => 'Diarsipkan (Archived)',
        ] as $k => $label) {
            $sel = ($status === $k) ? 'selected' : '';
            $statusOptions .= '<option value="' . $k . '" ' . $sel . '>' . $this->h($label) . '</option>';
        }

        $instructorOptions = '<option value="">-- Pilih Instruktur Utama --</option>';
        $currentInstId = (int)$this->val('instructor_id', 0);
        foreach ($instructors as $inst) {
            $sel = ((int)$inst['id'] === $currentInstId) ? 'selected' : '';
            $instructorOptions .= '<option value="' . (int)$inst['id'] . '" ' . $sel . '>' . $this->h($inst['name']) . ' (' . $this->h($inst['email']) . ')</option>';
        }

        $out = $this->renderStyles();
        $out .= '<form method="POST" enctype="multipart/form-data" id="lmsCourseForm" onsubmit="return validateCourseForm(this);">';
        $out .= '<input type="hidden" name="csrf_token" value="' . $this->h($csrfToken) . '">';
        $out .= '<input type="hidden" name="action" value="' . ($isEdit ? 'update_course' : 'save_course') . '">';
        $out .= '<input type="hidden" name="form_submit_mode" id="formSubmitMode" value="publish">';
        if ($isEdit) {
            $out .= '<input type="hidden" name="course_id" value="' . (int)$courseId . '">';
        }

        // SECTION 1: INFORMASI UTAMA (FIELD WAJIB)
        $out .= '<section class="lms-form-card">';
        $out .= '<h2 style="font-size:18px;margin-bottom:6px;color:var(--lms-form-gold-soft);">1. Informasi Utama Course (Field Wajib)</h2>';
        $out .= '<p class="lms-form-hint" style="margin-bottom:18px;">Semua field bertanda merah wajib diisi sebelum course dapat disimpan.</p>';
        $out .= '<div class="lms-form-grid">';

        $out .= '<div class="lms-form-field full">';
        $out .= '<label class="lms-form-label">Judul Course <span class="req">*</span></label>';
        $out .= '<input type="text" name="title" id="fieldTitle" required class="lms-dark-input" value="' . $this->h($this->val('title')) . '" placeholder="Contoh: Blueprint Cuan Emas 100 Gram Pertama">';
        $out .= '</div>';

        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Kategori <span class="req">*</span></label>';
        $out .= '<select name="category_id" id="fieldCategory" required class="lms-dark-select">' . $catOptions . '</select>';
        $out .= '</div>';

        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Status Kursus <span class="req">*</span></label>';
        $out .= '<select name="status" id="fieldStatus" required class="lms-dark-select">' . $statusOptions . '</select>';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full">';
        $out .= '<label class="lms-form-label">Deskripsi Lengkap <span class="req">*</span></label>';
        $out .= '<textarea name="description" id="fieldDescription" required rows="5" class="lms-dark-textarea" placeholder="Jelaskan silabus, target audiens, capaian belajar, dan manfaat yang didapatkan peserta...">' . $this->h($this->val('description')) . '</textarea>';
        $out .= '</div>';

        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Tipe Akses <span class="req">*</span></label>';
        $out .= '<select name="access_type" id="fieldAccessType" required class="lms-dark-select" onchange="togglePriceRequirement();">';
        $out .= '<option value="free" ' . ($accessType === 'free' ? 'selected' : '') . '>Free Access (Gratis)</option>';
        $out .= '<option value="premium" ' . ($accessType === 'premium' ? 'selected' : '') . '>Paid Access (Premium Berbayar)</option>';
        $out .= '</select>';
        $out .= '</div>';

        $priceDisplay = ($accessType === 'premium') ? 'grid' : 'none';
        $out .= '<div class="lms-form-field" id="wrapPriceField" style="display:' . $priceDisplay . ';">';
        $out .= '<label class="lms-form-label" id="labelPrice">Harga Kursus (Rp) <span class="req">*</span></label>';
        $out .= '<input type="number" name="price" id="fieldPrice" min="0" step="1000" class="lms-dark-input" value="' . (int)$this->val('price', 0) . '" placeholder="Contoh: 299000">';
        $out .= '<span class="lms-form-hint">Wajib bernilai di atas 0 jika tipe akses premium.</span>';
        $out .= '</div>';

        $out .= '</div>'; // grid
        $out .= '</section>';

        // SECTION 2: METADATA & PARAMETER OPSIONAL
        $out .= '<section class="lms-form-card">';
        $out .= '<h2 style="font-size:18px;margin-bottom:6px;color:var(--lms-form-gold-soft);">2. Metadata & Parameter Tambahan (Opsional)</h2>';
        $out .= '<p class="lms-form-hint" style="margin-bottom:18px;">Lengkapi parameter pendukung untuk pencarian, level kompetensi, dan branding materi.</p>';
        $out .= '<div class="lms-form-grid">';

        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Level Kompetensi</label>';
        $out .= '<select name="level" class="lms-dark-select">' . $levelOptions . '</select>';
        $out .= '</div>';

        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Estimasi Durasi Belajar</label>';
        $out .= '<div style="display:flex;gap:8px;">';
        $out .= '<input type="number" name="duration_hours" min="0" class="lms-dark-input" value="' . $durationHours . '" placeholder="Jam" style="flex:1;">';
        $out .= '<input type="number" name="duration_minutes" min="0" max="59" class="lms-dark-input" value="' . $durationRemainingMinutes . '" placeholder="Menit" style="flex:1;">';
        $out .= '</div>';
        $out .= '<span class="lms-form-hint">Contoh: 2 Jam 30 Menit.</span>';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full">';
        $out .= '<label class="lms-form-label">Tag / Keyword Pencarian</label>';
        $out .= '<input type="text" name="tags" class="lms-dark-input" value="' . $this->h($tagsString) . '" placeholder="emas batangan, investasi, cashflow, lindung nilai (pisahkan dengan koma)">';
        $out .= '<span class="lms-form-hint">Digunakan untuk pencarian katalog dan relasi course terkait.</span>';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full">';
        $out .= '<label class="lms-form-label">Slug URL Kustom</label>';
        $out .= '<input type="text" name="slug" class="lms-dark-input" value="' . $this->h($this->val('slug')) . '" placeholder="Otomatis dari judul jika dikosongkan">';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full">';
        $out .= '<label class="lms-form-label">Ringkasan Singkat (Summary Catalog)</label>';
        $out .= '<input type="text" name="summary" class="lms-dark-input" value="' . $this->h($this->val('summary')) . '" placeholder="1-2 kalimat untuk preview card katalog">';
        $out .= '</div>';

        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Badge Promo Label</label>';
        $out .= '<input type="text" name="badge_label" class="lms-dark-input" value="' . $this->h($this->val('badge_label')) . '" placeholder="Best Seller / Populer / Baru">';
        $out .= '</div>';

        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Pengajar / Instruktur (Scalability)</label>';
        $out .= '<select name="instructor_id" class="lms-dark-select">' . $instructorOptions . '</select>';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full">';
        $out .= '<label class="lms-checkbox-label">';
        $out .= '<input type="checkbox" name="certificate_enabled" value="1" ' . $certChecked . ' style="accent-color:var(--lms-form-gold);width:18px;height:18px;">';
        $out .= '<span>Aktifkan Sertifikat Digital Resmi saat peserta tuntas 100%</span>';
        $out .= '</label>';
        $out .= '</div>';

        $out .= '</div>'; // grid
        $out .= '</section>';

        // SECTION 3: UPLOAD MEDIA & MATERI LANJUTAN
        $out .= '<section class="lms-form-card">';
        $out .= '<h2 style="font-size:18px;margin-bottom:6px;color:var(--lms-form-gold-soft);">3. Upload Media & Materi Pembelajaran</h2>';
        $out .= '<p class="lms-form-hint" style="margin-bottom:18px;">Upload thumbnail gambar, file video / PDF modul starter, atau tautan video eksternal.</p>';
        $out .= '<div class="lms-form-grid">';

        $coverImg = (string)$this->val('cover_image', '');
        $out .= '<div class="lms-form-field full">';
        $out .= '<label class="lms-form-label">Thumbnail / Cover Image (JPG, PNG, WebP max 5MB)</label>';
        if ($coverImg !== '') {
            $out .= '<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">';
            $out .= '<img src="' . $this->h($coverImg) . '" alt="Cover" style="height:60px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);">';
            $out .= '<span class="lms-form-hint">Cover saat ini: ' . $this->h(basename($coverImg)) . '</span>';
            $out .= '</div>';
        }
        $out .= '<input type="file" name="cover_file" accept=".jpg,.jpeg,.png,.webp" class="lms-dark-input" style="padding:8px;">';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full" style="background:rgba(255,255,255,0.02);padding:16px;border-radius:14px;border:1px solid var(--lms-form-control-border);">';
        $out .= '<div style="font-size:14px;font-weight:700;margin-bottom:8px;color:var(--lms-form-gold-soft);">+ Tambah Materi Starter Cepat (Opsional)</div>';
        $out .= '<div class="lms-form-grid">';
        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Tipe Materi Starter</label>';
        $out .= '<select name="quick_material_type" id="quickMaterialType" class="lms-dark-select" onchange="toggleMaterialInputs();">';
        $out .= '<option value="">-- Lewati Materi Starter --</option>';
        $out .= '<option value="video">Video Pembelajaran</option>';
        $out .= '<option value="pdf">Modul Dokumen PDF</option>';
        $out .= '<option value="quiz">Kuis Singkat</option>';
        $out .= '</select>';
        $out .= '</div>';

        $out .= '<div class="lms-form-field">';
        $out .= '<label class="lms-form-label">Judul Materi Starter</label>';
        $out .= '<input type="text" name="quick_material_title" class="lms-dark-input" placeholder="Contoh: Pengantar Strategi">';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full" id="wrapMaterialUpload" style="display:none;">';
        $out .= '<label class="lms-form-label">Upload File Materi (MP4/WebM max 100MB, PDF max 20MB)</label>';
        $out .= '<input type="file" name="quick_material_file" id="fieldMaterialFile" class="lms-dark-input" style="padding:8px;">';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full" id="wrapMaterialUrl" style="display:none;">';
        $out .= '<label class="lms-form-label">Atau URL Video / Resource Eksternal (YouTube/Vimeo/Cloud)</label>';
        $out .= '<input type="url" name="quick_material_url" class="lms-dark-input" placeholder="https://www.youtube.com/embed/...">';
        $out .= '</div>';

        $out .= '<div class="lms-form-field full" id="wrapMaterialQuiz" style="display:none;">';
        $out .= '<label class="lms-form-label">Pertanyaan Kuis Starter (JSON Opsional atau Teks)</label>';
        $out .= '<textarea name="quick_quiz_json" rows="3" class="lms-dark-textarea" placeholder="Contoh: {&quot;passing_score&quot;:70, &quot;questions&quot;:[...]}&#10;Atau biarkan kosong untuk kuis default otomatis."></textarea>';
        $out .= '</div>';

        $out .= '</div>'; // inner grid
        $out .= '</div>'; // box

        $out .= '</div>'; // grid
        $out .= '</section>';

        // ACTIONS BAR
        $out .= '<div class="lms-actions-bar">';
        $out .= '<div style="display:flex;align-items:center;gap:10px;">';
        $out .= '<a href="lms-courses.php" class="lms-btn lms-btn-cancel">✕ Cancel</a>';
        if ($isEdit) {
            $out .= '<a href="' . $this->h($previewUrl) . '" target="_blank" class="lms-btn lms-btn-preview">👁 Buka Preview Asli ↗</a>';
        }
        $out .= '<button type="button" class="lms-btn lms-btn-preview" onclick="openLivePreviewModal();">🔍 Live Preview Form</button>';
        $out .= '</div>';

        $out .= '<div style="display:flex;align-items:center;gap:10px;">';
        $out .= '<button type="submit" onclick="document.getElementById(\'formSubmitMode\').value=\'draft\';" class="lms-btn lms-btn-draft">💾 Save Draft</button>';
        $out .= '<button type="submit" onclick="document.getElementById(\'formSubmitMode\').value=\'publish\';" class="lms-btn lms-btn-publish">🚀 Publish Course</button>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</form>';

        // MODAL PREVIEW
        $out .= '<div id="lmsPreviewModal" class="lms-modal-overlay">';
        $out .= '<div class="lms-modal">';
        $out .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">';
        $out .= '<h3 style="font-size:18px;margin:0;color:var(--lms-form-gold-soft);">Preview Tampilan Kursus Siswa</h3>';
        $out .= '<button type="button" onclick="closeLivePreviewModal();" class="lms-btn lms-btn-cancel" style="padding:6px 12px;font-size:12px;">✕ Tutup</button>';
        $out .= '</div>';
        $out .= '<div id="lmsPreviewContent" style="background:#111110;border-radius:16px;padding:20px;border:1px solid rgba(255,255,255,0.1);"></div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= <<<'JS'
<script>
function togglePriceRequirement() {
  const type = document.getElementById('fieldAccessType').value;
  const wrap = document.getElementById('wrapPriceField');
  const priceInput = document.getElementById('fieldPrice');
  if (type === 'premium') {
    wrap.style.display = 'grid';
    priceInput.required = true;
  } else {
    wrap.style.display = 'none';
    priceInput.required = false;
  }
}

function toggleMaterialInputs() {
  const mType = document.getElementById('quickMaterialType').value;
  const wrapUpload = document.getElementById('wrapMaterialUpload');
  const wrapUrl = document.getElementById('wrapMaterialUrl');
  const wrapQuiz = document.getElementById('wrapMaterialQuiz');

  wrapUpload.style.display = (mType === 'video' || mType === 'pdf') ? 'grid' : 'none';
  wrapUrl.style.display = (mType === 'video') ? 'grid' : 'none';
  wrapQuiz.style.display = (mType === 'quiz') ? 'grid' : 'none';
}

function validateCourseForm(form) {
  const title = document.getElementById('fieldTitle').value.trim();
  const desc = document.getElementById('fieldDescription').value.trim();
  const cat = document.getElementById('fieldCategory').value.trim();
  const status = document.getElementById('fieldStatus').value.trim();
  const access = document.getElementById('fieldAccessType').value;
  const price = parseInt(document.getElementById('fieldPrice').value, 10) || 0;

  if (!title) {
    alert('Judul course wajib diisi.');
    document.getElementById('fieldTitle').focus();
    return false;
  }
  if (!cat) {
    alert('Kategori wajib dipilih.');
    document.getElementById('fieldCategory').focus();
    return false;
  }
  if (!status) {
    alert('Status wajib dipilih.');
    document.getElementById('fieldStatus').focus();
    return false;
  }
  if (!desc) {
    alert('Deskripsi wajib diisi.');
    document.getElementById('fieldDescription').focus();
    return false;
  }
  if (access === 'premium' && price <= 0) {
    alert('Course berbayar (Premium) wajib memiliki harga di atas 0.');
    document.getElementById('fieldPrice').focus();
    return false;
  }
  return true;
}

function openLivePreviewModal() {
  const title = document.getElementById('fieldTitle').value.trim() || 'Judul Kursus Belum Diisi';
  const desc = document.getElementById('fieldDescription').value.trim() || 'Deskripsi belum diisi...';
  const catSelect = document.getElementById('fieldCategory');
  const catName = catSelect.options[catSelect.selectedIndex] ? catSelect.options[catSelect.selectedIndex].text : '-';
  const access = document.getElementById('fieldAccessType').value;
  const price = parseInt(document.getElementById('fieldPrice').value, 10) || 0;
  const status = document.getElementById('fieldStatus').value;

  const content = document.getElementById('lmsPreviewContent');
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:12px;flex-wrap:wrap;">
      <span style="font-size:11px;font-weight:800;padding:4px 10px;border-radius:999px;background:${access==='free'?'rgba(59,130,246,0.2)':'rgba(214,165,54,0.25)'};color:${access==='free'?'#93c5fd':'#f4d27a'};border:1px solid rgba(255,255,255,0.1);">
        ${access === 'free' ? 'FREE ACCESS' : 'PREMIUM COURSE'}
      </span>
      <span style="font-size:12px;color:#a8a29a;">Kategori: <strong style="color:#fff;">${catName}</strong></span>
      <span style="font-size:12px;color:#a8a29a;">Status: <strong style="color:#fff;">${status.toUpperCase()}</strong></span>
    </div>
    <h2 style="font-size:22px;margin:0 0 10px;color:#fff;">${title}</h2>
    <p style="font-size:13.5px;color:#a8a29a;line-height:1.6;white-space:pre-wrap;margin-bottom:16px;">${desc}</p>
    <div style="padding:14px;background:rgba(255,255,255,0.04);border-radius:12px;display:flex;justify-content:space-between;align-items:center;">
      <span style="font-size:13px;color:#a8a29a;">Biaya Kursus</span>
      <span style="font-size:20px;font-weight:800;color:${access==='free'?'#93c5fd':'#f4d27a'};">
        ${access === 'free' ? 'GRATIS' : 'Rp ' + price.toLocaleString('id-ID')}
      </span>
    </div>
  `;
  document.getElementById('lmsPreviewModal').style.display = 'grid';
}

function closeLivePreviewModal() {
  document.getElementById('lmsPreviewModal').style.display = 'none';
}
</script>
JS;

        return $out;
    }
}
