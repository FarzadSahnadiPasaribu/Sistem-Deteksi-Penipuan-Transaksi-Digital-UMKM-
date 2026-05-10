"""
Generate Sequence Diagram, Activity Diagram, dan DFD untuk CampusVents.
Output: PNG files di folder diagrams/
"""

import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
from matplotlib.patches import FancyArrowPatch, FancyBboxPatch
import matplotlib.patheffects as pe
import numpy as np

OUT = "diagrams"

BLUE_DARK  = "#1a3a5c"
BLUE_MID   = "#2563eb"
BLUE_LIGHT = "#dbeafe"
BLUE_PALE  = "#eff6ff"
GREEN      = "#16a34a"
GREEN_LIGHT= "#dcfce7"
ORANGE     = "#ea580c"
ORANGE_LIGHT="#fed7aa"
PURPLE     = "#7c3aed"
PURPLE_LIGHT="#ede9fe"
RED        = "#dc2626"
RED_LIGHT  = "#fee2e2"
GRAY       = "#6b7280"
GRAY_LIGHT = "#f3f4f6"
GRAY_MED   = "#e5e7eb"
WHITE      = "#ffffff"
BLACK      = "#111827"

# ─────────────────────────────────────────────────────────────────────────────
# SEQUENCE DIAGRAM
# ─────────────────────────────────────────────────────────────────────────────
def draw_sequence_diagram():
    fig, ax = plt.subplots(figsize=(18, 22))
    fig.patch.set_facecolor(GRAY_LIGHT)
    ax.set_facecolor(GRAY_LIGHT)
    ax.set_xlim(0, 18)
    ax.set_ylim(0, 22)
    ax.axis('off')

    # Title
    ax.text(9, 21.3, "Sequence Diagram — CampusVents",
            ha='center', va='center', fontsize=20, fontweight='bold',
            color=BLUE_DARK,
            bbox=dict(boxstyle='round,pad=0.5', facecolor=BLUE_LIGHT, edgecolor=BLUE_MID, lw=2))

    # Actors / Lifelines
    actors = [
        ("Mahasiswa",  2.0,  BLUE_MID,    BLUE_LIGHT),
        ("Browser",    5.0,  PURPLE,      PURPLE_LIGHT),
        ("PHP Server", 9.0,  GREEN,       GREEN_LIGHT),
        ("Database",   13.5, ORANGE,      ORANGE_LIGHT),
        ("Admin/\nPanitia", 16.5, RED,   RED_LIGHT),
    ]

    lifeline_top = 20.5
    lifeline_bot = 0.4

    for name, x, color, bg in actors:
        box = FancyBboxPatch((x-1.1, lifeline_top-0.35), 2.2, 0.7,
                             boxstyle="round,pad=0.05",
                             facecolor=bg, edgecolor=color, lw=2)
        ax.add_patch(box)
        ax.text(x, lifeline_top, name, ha='center', va='center',
                fontsize=9, fontweight='bold', color=color)
        ax.plot([x, x], [lifeline_top-0.35, lifeline_bot],
                color=color, lw=1.5, ls='--', alpha=0.5, zorder=1)

    def msg(y, x1, x2, label, color=BLACK, style='solid', ret=False, note=None):
        """Draw a message arrow between lifelines."""
        ax.annotate("", xy=(x2, y), xytext=(x1, y),
                    arrowprops=dict(
                        arrowstyle="->", color=color, lw=1.5,
                        linestyle='dashed' if ret else 'solid',
                        connectionstyle="arc3,rad=0"
                    ))
        mx = (x1 + x2) / 2
        ax.text(mx, y + 0.13, label, ha='center', va='bottom',
                fontsize=7.5, color=color, style='italic' if ret else 'normal',
                bbox=dict(boxstyle='round,pad=0.15', facecolor=WHITE, edgecolor='none', alpha=0.8))
        if note:
            ax.text(x2 + 0.15, y, note, ha='left', va='center',
                    fontsize=6.5, color=GRAY,
                    bbox=dict(boxstyle='round,pad=0.1', facecolor=GRAY_LIGHT, edgecolor=GRAY, lw=0.5))

    def section(y, label, color):
        ax.add_patch(mpatches.FancyBboxPatch((0.1, y-0.18), 17.8, 0.36,
                     boxstyle="round,pad=0.05", facecolor=color, edgecolor='none', alpha=0.3, zorder=0))
        ax.text(0.4, y, label, ha='left', va='center',
                fontsize=8, fontweight='bold', color=BLUE_DARK)

    def activation(x, y_start, y_end, color):
        ax.add_patch(FancyBboxPatch((x-0.12, y_end), 0.24, y_start-y_end,
                     boxstyle="square,pad=0", facecolor=color, edgecolor='none', alpha=0.35))

    # ── Section 1: Register ──────────────────────────────────────────────────
    section(19.8, "① Registrasi Akun", BLUE_LIGHT)
    msg(19.3, 2.0, 5.0, "GET /register.php", BLUE_MID)
    msg(18.8, 5.0, 9.0, "Request halaman register", PURPLE)
    msg(18.3, 9.0, 5.0, "HTML form register", GREEN, ret=True)
    msg(17.8, 5.0, 2.0, "Tampilkan form", PURPLE, ret=True)
    msg(17.2, 2.0, 5.0, "POST (nama, email, password, role)", BLUE_MID)
    msg(16.7, 5.0, 9.0, "Submit data registrasi", PURPLE)
    activation(9.0, 16.7, 15.6, GREEN)
    msg(16.2, 9.0, 13.5, "INSERT INTO users", GREEN)
    msg(15.7, 13.5, 9.0, "User ID baru", ORANGE, ret=True)
    msg(15.2, 9.0, 5.0, "Redirect ke login", GREEN, ret=True)
    msg(14.7, 5.0, 2.0, "Halaman login", PURPLE, ret=True)

    # ── Section 2: Login ────────────────────────────────────────────────────
    section(14.2, "② Login & Autentikasi", GREEN_LIGHT)
    msg(13.7, 2.0, 5.0, "POST /login.php (email, password)", BLUE_MID)
    msg(13.2, 5.0, 9.0, "Verifikasi kredensial", PURPLE)
    activation(9.0, 13.2, 11.6, GREEN)
    msg(12.7, 9.0, 13.5, "SELECT user WHERE email=?", GREEN)
    msg(12.2, 13.5, 9.0, "Data user + password hash", ORANGE, ret=True)
    msg(11.7, 9.0, 5.0, "Set session + redirect dashboard", GREEN, ret=True)
    msg(11.2, 5.0, 2.0, "Dashboard sesuai role", PURPLE, ret=True)

    # ── Section 3: Lihat & Daftar Event ─────────────────────────────────────
    section(10.7, "③ Lihat & Daftar Event (Mahasiswa)", PURPLE_LIGHT)
    msg(10.2, 2.0, 5.0, "GET /mahasiswa/events.php", BLUE_MID)
    msg(9.7, 5.0, 9.0, "Request daftar event", PURPLE)
    activation(9.0, 9.7, 7.9, GREEN)
    msg(9.2, 9.0, 13.5, "SELECT events JOIN categories\nWHERE status='published'", GREEN)
    msg(8.7, 13.5, 9.0, "List event tersedia", ORANGE, ret=True)
    msg(8.2, 9.0, 5.0, "HTML daftar event + rekomendasi", GREEN, ret=True)
    msg(7.8, 5.0, 2.0, "Tampilkan event", PURPLE, ret=True)
    msg(7.3, 2.0, 5.0, "POST /mahasiswa/register_event.php", BLUE_MID)
    msg(6.8, 5.0, 9.0, "Daftar ke event (event_id)", PURPLE)
    activation(9.0, 6.8, 5.1, GREEN)
    msg(6.3, 9.0, 13.5, "INSERT registrations", GREEN)
    msg(5.8, 13.5, 9.0, "Registration ID", ORANGE, ret=True)
    msg(5.3, 9.0, 13.5, "INSERT notifications (panitia)", GREEN)
    msg(4.8, 9.0, 5.0, "Kode registrasi + konfirmasi", GREEN, ret=True)
    msg(4.3, 5.0, 2.0, "Tampilkan konfirmasi", PURPLE, ret=True)

    # ── Section 4: Panitia kelola event ─────────────────────────────────────
    section(3.8, "④ Panitia Buat Event Baru", ORANGE_LIGHT)
    msg(3.3, 16.5, 5.0, "POST /panitia/create_event.php", RED)
    msg(2.8, 5.0, 9.0, "Submit data event + poster", PURPLE)
    activation(9.0, 2.8, 1.3, GREEN)
    msg(2.3, 9.0, 13.5, "INSERT events, upload poster", GREEN)
    msg(1.8, 13.5, 9.0, "Event ID", ORANGE, ret=True)
    msg(1.3, 9.0, 13.5, "INSERT notifications (interested users)", GREEN)
    msg(0.8, 9.0, 5.0, "Redirect ke detail event", GREEN, ret=True)

    plt.tight_layout(pad=0.5)
    plt.savefig(f"{OUT}/sequence_diagram.png", dpi=150, bbox_inches='tight',
                facecolor=GRAY_LIGHT)
    plt.close()
    print("✓ sequence_diagram.png")


# ─────────────────────────────────────────────────────────────────────────────
# ACTIVITY DIAGRAM
# ─────────────────────────────────────────────────────────────────────────────
def draw_activity_diagram():
    fig, axes = plt.subplots(1, 3, figsize=(21, 24))
    fig.patch.set_facecolor(GRAY_LIGHT)

    fig.text(0.5, 0.985, "Activity Diagram — CampusVents",
             ha='center', va='top', fontsize=20, fontweight='bold', color=BLUE_DARK,
             bbox=dict(boxstyle='round,pad=0.4', facecolor=BLUE_LIGHT, edgecolor=BLUE_MID, lw=2))

    titles = ["Alur Registrasi & Login", "Alur Daftar Event (Mahasiswa)", "Alur Kelola Event (Panitia/Admin)"]
    colors = [BLUE_MID, GREEN, ORANGE]

    for ax, title, col in zip(axes, titles, colors):
        ax.set_facecolor(GRAY_LIGHT)
        ax.set_xlim(0, 10)
        ax.set_ylim(0, 24)
        ax.axis('off')
        ax.text(5, 23.5, title, ha='center', va='center', fontsize=11, fontweight='bold',
                color=WHITE,
                bbox=dict(boxstyle='round,pad=0.4', facecolor=col, edgecolor='none'))

    def node(ax, x, y, text, shape='rect', color=BLUE_MID, w=3.8, h=0.55, fontsize=8):
        bg = color + '22' if len(color) == 7 else color
        if shape == 'start':
            circle = plt.Circle((x, y), 0.25, color=BLACK, zorder=5)
            ax.add_patch(circle)
        elif shape == 'end':
            circle = plt.Circle((x, y), 0.28, color=WHITE, ec=BLACK, lw=2, zorder=5)
            ax.add_patch(circle)
            circle2 = plt.Circle((x, y), 0.18, color=BLACK, zorder=6)
            ax.add_patch(circle2)
        elif shape == 'decision':
            diamond_x = [x, x+w/2, x, x-w/2, x]
            diamond_y = [y+h*0.9, y, y-h*0.9, y, y+h*0.9]
            ax.fill(diamond_x, diamond_y, color=ORANGE_LIGHT, zorder=3)
            ax.plot(diamond_x, diamond_y, color=ORANGE, lw=1.5, zorder=4)
            ax.text(x, y, text, ha='center', va='center', fontsize=fontsize-0.5,
                    fontweight='bold', color=ORANGE, zorder=5, wrap=True)
        elif shape == 'fork':
            ax.add_patch(FancyBboxPatch((x-w/2, y-0.08), w, 0.16,
                         boxstyle="square,pad=0", facecolor=BLACK, zorder=5))
        else:
            ax.add_patch(FancyBboxPatch((x-w/2, y-h/2), w, h,
                         boxstyle="round,pad=0.08",
                         facecolor=color + '33', edgecolor=color, lw=1.5, zorder=3))
            ax.text(x, y, text, ha='center', va='center', fontsize=fontsize,
                    color=BLACK, zorder=4, wrap=True,
                    multialignment='center')

    def arrow(ax, x1, y1, x2, y2, label='', color=GRAY):
        ax.annotate("", xy=(x2, y2+0.28), xytext=(x1, y1-0.28),
                    arrowprops=dict(arrowstyle="-|>", color=color, lw=1.3),
                    zorder=2)
        if label:
            mx, my = (x1+x2)/2, (y1+y2)/2
            ax.text(mx+0.15, my, label, fontsize=7, color=GRAY,
                    bbox=dict(boxstyle='round,pad=0.1', facecolor=WHITE, edgecolor='none', alpha=0.7))

    def arrow_dec(ax, x1, y1, x2, y2, label='', color=ORANGE):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="-|>", color=color, lw=1.2,
                                   connectionstyle="arc3,rad=0.0"), zorder=2)
        if label:
            mx, my = (x1+x2)/2+0.2, (y1+y2)/2
            ax.text(mx, my, label, fontsize=7, color=ORANGE, fontweight='bold',
                    bbox=dict(boxstyle='round,pad=0.1', facecolor=WHITE, edgecolor='none', alpha=0.8))

    # ── Diagram 1: Registrasi & Login ────────────────────────────────────────
    ax = axes[0]
    c = BLUE_MID
    ys = np.linspace(22.5, 1.0, 22)

    node(ax, 5, ys[0], '', 'start')
    node(ax, 5, ys[1], 'Buka Aplikasi CampusVents', 'rect', c)
    arrow(ax, 5, ys[0], 5, ys[1])
    node(ax, 5, ys[2], 'Sudah punya\nakun?', 'decision')
    arrow(ax, 5, ys[1], 5, ys[2])

    # NO → Daftar
    node(ax, 2.5, ys[3], 'Buka halaman Register', 'rect', c)
    arrow_dec(ax, 3.0, ys[2]-0.45, 2.5, ys[3]+0.28, 'Tidak')
    node(ax, 2.5, ys[4], 'Isi form:\nnama, email, password, role', 'rect', c)
    arrow(ax, 2.5, ys[3], 2.5, ys[4])
    node(ax, 2.5, ys[5], 'Data\nvalid?', 'decision')
    arrow(ax, 2.5, ys[4], 2.5, ys[5])
    node(ax, 1.2, ys[5], 'Tampilkan\nerror', 'rect', RED, w=2.0)
    arrow_dec(ax, 1.8, ys[5], 1.2, ys[5], 'Tidak')
    node(ax, 2.5, ys[6], 'Simpan user ke DB\n(password hash)', 'rect', c)
    arrow_dec(ax, 2.5, ys[5]-0.45, 2.5, ys[6]+0.28, 'Ya')
    node(ax, 2.5, ys[7], 'Kirim ke halaman Login', 'rect', c)
    arrow(ax, 2.5, ys[6], 2.5, ys[7])

    # YES → Login
    node(ax, 7.5, ys[3], 'Buka halaman Login', 'rect', c)
    arrow_dec(ax, 7.0, ys[2]-0.45, 7.5, ys[3]+0.28, 'Ya')
    node(ax, 7.5, ys[4], 'Masukkan email\n& password', 'rect', c)
    arrow(ax, 7.5, ys[3], 7.5, ys[4])
    node(ax, 7.5, ys[5], 'Kredensial\nbenar?', 'decision')
    arrow(ax, 7.5, ys[4], 7.5, ys[5])
    node(ax, 9.0, ys[5], 'Tampilkan\nerror', 'rect', RED, w=2.0)
    arrow_dec(ax, 8.2, ys[5], 9.0, ys[5], 'Tidak')
    node(ax, 7.5, ys[6], 'Buat session user\n(role, id, name)', 'rect', c)
    arrow_dec(ax, 7.5, ys[5]-0.45, 7.5, ys[6]+0.28, 'Ya')
    node(ax, 7.5, ys[7], 'Redirect dashboard\nsesuai role', 'rect', c)
    arrow(ax, 7.5, ys[6], 7.5, ys[7])

    # Merge
    node(ax, 5, ys[8], 'Dashboard utama', 'rect', c)
    arrow(ax, 2.5, ys[7], 5, ys[8])
    arrow(ax, 7.5, ys[7], 5, ys[8])
    node(ax, 5, ys[9], '', 'end')
    arrow(ax, 5, ys[8], 5, ys[9])

    # ── Diagram 2: Daftar Event ──────────────────────────────────────────────
    ax = axes[1]
    c = GREEN
    ys2 = np.linspace(22.5, 1.0, 20)

    node(ax, 5, ys2[0], '', 'start')
    node(ax, 5, ys2[1], 'Dashboard Mahasiswa', 'rect', c)
    arrow(ax, 5, ys2[0], 5, ys2[1])
    node(ax, 5, ys2[2], 'Buka halaman Events', 'rect', c)
    arrow(ax, 5, ys2[1], 5, ys2[2])
    node(ax, 5, ys2[3], 'Ambil data event\n(published, deadline > now)', 'rect', c)
    arrow(ax, 5, ys2[2], 5, ys2[3])
    node(ax, 5, ys2[4], 'Tampilkan list +\nrekomendasi berdasar minat', 'rect', c)
    arrow(ax, 5, ys2[3], 5, ys2[4])
    node(ax, 5, ys2[5], 'Pilih event', 'rect', c)
    arrow(ax, 5, ys2[4], 5, ys2[5])
    node(ax, 5, ys2[6], 'Quota\ntersisa?', 'decision')
    arrow(ax, 5, ys2[5], 5, ys2[6])
    node(ax, 2.0, ys2[6], 'Tampilkan\n"Kuota Penuh"', 'rect', RED, w=2.8)
    arrow_dec(ax, 3.0, ys2[6], 2.0, ys2[6], 'Tidak')
    node(ax, 5, ys2[7], 'Sudah\ndaftar?', 'decision')
    arrow_dec(ax, 5, ys2[6]-0.45, 5, ys2[7]+0.28, 'Ya')
    node(ax, 2.0, ys2[7], 'Tampilkan\n"Sudah Terdaftar"', 'rect', ORANGE, w=2.8)
    arrow_dec(ax, 3.0, ys2[7], 2.0, ys2[7], 'Ya')
    node(ax, 5, ys2[8], 'Klik tombol Daftar', 'rect', c)
    arrow_dec(ax, 5, ys2[7]-0.45, 5, ys2[8]+0.28, 'Tidak')
    node(ax, 5, ys2[9], 'Insert registrasi\n+ generate kode', 'rect', c)
    arrow(ax, 5, ys2[8], 5, ys2[9])
    node(ax, 5, ys2[10], 'Kirim notifikasi\nke Panitia', 'rect', c)
    arrow(ax, 5, ys2[9], 5, ys2[10])
    node(ax, 5, ys2[11], 'Tampilkan kode\nregistrasi & konfirmasi', 'rect', c)
    arrow(ax, 5, ys2[10], 5, ys2[11])
    node(ax, 5, ys2[12], 'Lihat riwayat\npendaftaran', 'rect', c)
    arrow(ax, 5, ys2[11], 5, ys2[12])
    node(ax, 5, ys2[13], '', 'end')
    arrow(ax, 5, ys2[12], 5, ys2[13])

    # ── Diagram 3: Kelola Event ──────────────────────────────────────────────
    ax = axes[2]
    c = ORANGE
    ys3 = np.linspace(22.5, 1.0, 22)

    node(ax, 5, ys3[0], '', 'start')
    node(ax, 5, ys3[1], 'Login sebagai\nPanitia / Admin', 'rect', c)
    arrow(ax, 5, ys3[0], 5, ys3[1])
    node(ax, 5, ys3[2], 'Dashboard Panitia', 'rect', c)
    arrow(ax, 5, ys3[1], 5, ys3[2])

    # Fork: 2 paths
    node(ax, 5, ys3[3], '', 'fork', w=6)
    arrow(ax, 5, ys3[2], 5, ys3[3])

    # Left: Buat Event
    node(ax, 2.5, ys3[4], 'Buat Event Baru', 'rect', c, w=3.5)
    ax.annotate("", xy=(2.5, ys3[4]+0.28), xytext=(3.5, ys3[3]),
                arrowprops=dict(arrowstyle="-|>", color=ORANGE, lw=1.2))
    node(ax, 2.5, ys3[5], 'Isi detail event\n(judul, tgl, kuota, dll)', 'rect', c, w=3.5)
    arrow(ax, 2.5, ys3[4], 2.5, ys3[5])
    node(ax, 2.5, ys3[6], 'Upload poster', 'rect', c, w=3.5)
    arrow(ax, 2.5, ys3[5], 2.5, ys3[6])
    node(ax, 2.5, ys3[7], 'Data\nlengkap?', 'decision', w=2.5)
    arrow(ax, 2.5, ys3[6], 2.5, ys3[7])
    node(ax, 1.0, ys3[7], 'Error\nvalidasi', 'rect', RED, w=1.6)
    arrow_dec(ax, 1.6, ys3[7], 1.0, ys3[7], 'Tidak')
    node(ax, 2.5, ys3[8], 'Simpan ke DB\n(status: published)', 'rect', c, w=3.5)
    arrow_dec(ax, 2.5, ys3[7]-0.45, 2.5, ys3[8]+0.28, 'Ya')
    node(ax, 2.5, ys3[9], 'Notifikasi user\nberinat kategori ini', 'rect', c, w=3.5)
    arrow(ax, 2.5, ys3[8], 2.5, ys3[9])

    # Right: Kelola Peserta
    node(ax, 7.5, ys3[4], 'Kelola Peserta', 'rect', c, w=3.5)
    ax.annotate("", xy=(7.5, ys3[4]+0.28), xytext=(6.5, ys3[3]),
                arrowprops=dict(arrowstyle="-|>", color=ORANGE, lw=1.2))
    node(ax, 7.5, ys3[5], 'Lihat list peserta\nterdaftar', 'rect', c, w=3.5)
    arrow(ax, 7.5, ys3[4], 7.5, ys3[5])
    node(ax, 7.5, ys3[6], 'Verifikasi\nkehadiran', 'rect', c, w=3.5)
    arrow(ax, 7.5, ys3[5], 7.5, ys3[6])
    node(ax, 7.5, ys3[7], 'Update status\nregistrasi', 'rect', c, w=3.5)
    arrow(ax, 7.5, ys3[6], 7.5, ys3[7])
    node(ax, 7.5, ys3[8], 'Kirim notifikasi\nke peserta', 'rect', c, w=3.5)
    arrow(ax, 7.5, ys3[7], 7.5, ys3[8])
    node(ax, 7.5, ys3[9], 'Ekspor data\npeserta (CSV)', 'rect', c, w=3.5)
    arrow(ax, 7.5, ys3[8], 7.5, ys3[9])

    # Join
    node(ax, 5, ys3[10], '', 'fork', w=6)
    ax.annotate("", xy=(4.0, ys3[10]), xytext=(2.5, ys3[9]-0.28),
                arrowprops=dict(arrowstyle="-|>", color=ORANGE, lw=1.2))
    ax.annotate("", xy=(6.0, ys3[10]), xytext=(7.5, ys3[9]-0.28),
                arrowprops=dict(arrowstyle="-|>", color=ORANGE, lw=1.2))

    node(ax, 5, ys3[11], 'Lihat statistik event\n(dashboard admin)', 'rect', c)
    arrow(ax, 5, ys3[10], 5, ys3[11])
    node(ax, 5, ys3[12], '', 'end')
    arrow(ax, 5, ys3[11], 5, ys3[12])

    plt.tight_layout(pad=1.5, rect=[0, 0, 1, 0.97])
    plt.savefig(f"{OUT}/activity_diagram.png", dpi=150, bbox_inches='tight',
                facecolor=GRAY_LIGHT)
    plt.close()
    print("✓ activity_diagram.png")


# ─────────────────────────────────────────────────────────────────────────────
# DFD
# ─────────────────────────────────────────────────────────────────────────────
def draw_dfd():
    fig, axes = plt.subplots(1, 2, figsize=(22, 14))
    fig.patch.set_facecolor(GRAY_LIGHT)

    fig.text(0.5, 0.97, "Data Flow Diagram (DFD) — CampusVents",
             ha='center', va='top', fontsize=20, fontweight='bold', color=BLUE_DARK,
             bbox=dict(boxstyle='round,pad=0.4', facecolor=BLUE_LIGHT, edgecolor=BLUE_MID, lw=2))

    # ── DFD Level 0: Context Diagram ─────────────────────────────────────────
    ax = axes[0]
    ax.set_facecolor(GRAY_LIGHT)
    ax.set_xlim(0, 14)
    ax.set_ylim(0, 14)
    ax.axis('off')
    ax.text(7, 13.5, "Level 0 — Context Diagram",
            ha='center', va='center', fontsize=13, fontweight='bold', color=BLUE_DARK,
            bbox=dict(boxstyle='round,pad=0.3', facecolor=BLUE_LIGHT, edgecolor=BLUE_MID, lw=1.5))

    def ext_entity(ax, x, y, label, color=BLUE_MID):
        ax.add_patch(FancyBboxPatch((x-1.3, y-0.45), 2.6, 0.9,
                     boxstyle="square,pad=0.05",
                     facecolor=color+'22', edgecolor=color, lw=2))
        ax.text(x, y, label, ha='center', va='center', fontsize=9,
                fontweight='bold', color=color)

    def process_circle(ax, x, y, r, label, color=GREEN):
        circle = plt.Circle((x, y), r, facecolor=color+'22',
                            edgecolor=color, lw=2.5, zorder=3)
        ax.add_patch(circle)
        ax.text(x, y, label, ha='center', va='center', fontsize=8.5,
                fontweight='bold', color=color, zorder=4, multialignment='center')

    def datastore(ax, x, y, label, color=ORANGE, w=2.8, h=0.45):
        ax.add_patch(FancyBboxPatch((x-w/2, y-h/2), w, h,
                     boxstyle="square,pad=0",
                     facecolor=color+'22', edgecolor=color, lw=2))
        ax.plot([x-w/2, x+w/2], [y+h/2, y+h/2], color=color, lw=2)
        ax.text(x, y, label, ha='center', va='center', fontsize=8,
                fontweight='bold', color=color)

    def dfd_arrow(ax, x1, y1, x2, y2, label='', color=GRAY, bidirect=False):
        style = "<|-|>" if bidirect else "-|>"
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle=style, color=color, lw=1.4,
                                   connectionstyle="arc3,rad=0.0"), zorder=2)
        if label:
            mx, my = (x1+x2)/2, (y1+y2)/2
            offset_x = 0.2 if x1 == x2 else 0
            offset_y = 0.18 if y1 == y2 else 0
            ax.text(mx+offset_x, my+offset_y, label, ha='center', va='center',
                    fontsize=7, color=BLUE_DARK,
                    bbox=dict(boxstyle='round,pad=0.15', facecolor=WHITE,
                             edgecolor='none', alpha=0.85))

    # External entities
    ext_entity(ax, 2.0, 11.5, "Mahasiswa", BLUE_MID)
    ext_entity(ax, 12.0, 11.5, "Panitia", GREEN)
    ext_entity(ax, 2.0, 2.5, "Admin", RED)
    ext_entity(ax, 12.0, 2.5, "Sistem\nEmail/Notif", PURPLE)

    # Central process
    process_circle(ax, 7, 7, 2.2, "CAMPUSVENTS\nSistem Manajemen\nEvent Kampus", GREEN)

    # Arrows Mahasiswa ↔ Sistem
    dfd_arrow(ax, 3.3, 11.2, 5.0, 8.1, "Data registrasi,\npendaftaran event", BLUE_MID)
    dfd_arrow(ax, 5.0, 7.5, 3.3, 10.8, "Info event, kode\nregistrasi, notifikasi", BLUE_MID)

    # Arrows Panitia ↔ Sistem
    dfd_arrow(ax, 10.7, 11.2, 9.0, 8.1, "Data event, manajemen\npeserta", GREEN)
    dfd_arrow(ax, 9.0, 7.5, 10.7, 10.8, "Laporan peserta,\nstatistik event", GREEN)

    # Arrows Admin ↔ Sistem
    dfd_arrow(ax, 3.3, 2.8, 5.0, 5.9, "Konfigurasi sistem,\nkelola user & kategori", RED)
    dfd_arrow(ax, 5.0, 6.3, 3.3, 3.2, "Laporan lengkap,\ndata ekspor", RED)

    # Arrows Notif ↔ Sistem
    dfd_arrow(ax, 9.0, 6.3, 10.7, 2.8, "Trigger notifikasi\nevent baru", PURPLE)
    dfd_arrow(ax, 10.7, 3.2, 9.0, 5.9, "Konfirmasi\npengiriman", PURPLE)

    # ── DFD Level 1 ───────────────────────────────────────────────────────────
    ax = axes[1]
    ax.set_facecolor(GRAY_LIGHT)
    ax.set_xlim(0, 14)
    ax.set_ylim(0, 14)
    ax.axis('off')
    ax.text(7, 13.5, "Level 1 — Detail Proses",
            ha='center', va='center', fontsize=13, fontweight='bold', color=BLUE_DARK,
            bbox=dict(boxstyle='round,pad=0.3', facecolor=BLUE_LIGHT, edgecolor=BLUE_MID, lw=1.5))

    # External entities (smaller)
    ext_entity(ax, 1.3, 12.5, "Mahasiswa", BLUE_MID)
    ext_entity(ax, 7.0, 12.5, "Panitia", GREEN)
    ext_entity(ax, 12.7, 12.5, "Admin", RED)

    # Processes
    def proc(ax, x, y, num, label, color):
        ax.add_patch(plt.Circle((x, y), 0.9, facecolor=color+'25',
                               edgecolor=color, lw=2, zorder=3))
        ax.text(x, y+0.25, num, ha='center', va='center',
                fontsize=7, fontweight='bold', color=color, zorder=4)
        ax.text(x, y-0.2, label, ha='center', va='center',
                fontsize=6.5, color=BLACK, zorder=4, multialignment='center')

    proc(ax, 2.5, 10.0, "P1", "Autentikasi\n& Registrasi", BLUE_MID)
    proc(ax, 7.0, 10.0, "P2", "Manajemen\nEvent", GREEN)
    proc(ax, 11.5, 10.0, "P3", "Kelola\nUser & Kategori", RED)
    proc(ax, 2.5, 6.0,  "P4", "Pendaftaran\nEvent", BLUE_MID)
    proc(ax, 7.0, 6.0,  "P5", "Notifikasi &\nRekomendasi", PURPLE)
    proc(ax, 11.5, 6.0, "P6", "Laporan &\nEkspor Data", ORANGE)

    # Data stores
    datastore(ax, 2.5, 3.5, "D1: users", BLUE_MID)
    datastore(ax, 7.0, 3.5, "D2: events", GREEN)
    datastore(ax, 7.0, 2.0, "D3: categories", ORANGE)
    datastore(ax, 11.5, 3.5, "D4: registrations", PURPLE)
    datastore(ax, 11.5, 2.0, "D5: notifications", RED)
    datastore(ax, 2.5, 2.0, "D6: user_interests", GRAY)

    # External → Process
    dfd_arrow(ax, 1.3, 12.0, 1.9, 10.85, "login/register", BLUE_MID)
    dfd_arrow(ax, 7.0, 12.0, 7.0, 10.9,  "kelola event", GREEN)
    dfd_arrow(ax, 12.7, 12.0, 12.1, 10.85, "kelola user", RED)

    # Process → Process
    dfd_arrow(ax, 3.4, 10.0, 6.1, 10.0, "user_id,\nsession", BLUE_MID)
    dfd_arrow(ax, 7.9, 10.0, 10.6, 10.0, "event_id,\nkategori", GREEN)
    dfd_arrow(ax, 2.5, 9.1, 2.5, 6.9,  "user terauth", BLUE_MID)
    dfd_arrow(ax, 3.4, 6.0, 6.1, 6.0,  "trigger notif", BLUE_MID)
    dfd_arrow(ax, 7.9, 6.0, 10.6, 6.0, "data registrasi", PURPLE)
    dfd_arrow(ax, 7.0, 9.1, 7.0, 6.9,  "event published", GREEN)
    dfd_arrow(ax, 11.5, 9.1, 11.5, 6.9, "data peserta", RED)

    # Process → Datastore
    dfd_arrow(ax, 2.5, 5.1, 2.5, 4.2,  "r/w user", BLUE_MID)
    dfd_arrow(ax, 7.0, 5.1, 7.0, 4.2,  "r/w event", GREEN)
    dfd_arrow(ax, 11.5, 5.1, 11.5, 4.2, "r/w registrasi", PURPLE)
    dfd_arrow(ax, 6.1, 6.0, 2.5, 2.75, "r minat user", GRAY)
    dfd_arrow(ax, 7.0, 3.0, 7.0, 2.45, "r kategori", ORANGE)
    dfd_arrow(ax, 7.9, 6.0, 11.5, 2.75, "w notifikasi", RED)
    dfd_arrow(ax, 10.6, 6.0, 11.5, 4.2, "r reg. data", ORANGE)

    plt.tight_layout(pad=1.5, rect=[0, 0, 1, 0.95])
    plt.savefig(f"{OUT}/dfd.png", dpi=150, bbox_inches='tight',
                facecolor=GRAY_LIGHT)
    plt.close()
    print("✓ dfd.png")


if __name__ == '__main__':
    import os
    os.makedirs(OUT, exist_ok=True)
    draw_sequence_diagram()
    draw_activity_diagram()
    draw_dfd()
    print("\nSemua diagram berhasil dibuat di folder diagrams/")
