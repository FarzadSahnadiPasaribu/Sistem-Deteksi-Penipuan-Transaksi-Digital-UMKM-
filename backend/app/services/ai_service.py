"""
Layanan Claude AI untuk penjelasan hasil deteksi penipuan.

Fix utama: filter content block kosong sebelum dikirim ke API agar tidak
memicu error "text content blocks must be non-empty".
"""

import os
from typing import Optional

import anthropic


def _build_prompt(fraud_result: dict) -> str:
    """Susun prompt dari hasil deteksi; pastikan tidak pernah kosong."""
    fraud_score = fraud_result.get("fraud_score", 0)
    risk_level = fraud_result.get("risk_level", "TIDAK DIKETAHUI")
    is_fraud = fraud_result.get("is_fraud", False)

    # Filter string kosong/whitespace — penyebab utama error "non-empty"
    raw_explanations = fraud_result.get("explanation", []) or []
    explanations = [e.strip() for e in raw_explanations if e and e.strip()]

    lines = [
        "Kamu adalah analis keamanan transaksi digital untuk UMKM Indonesia.",
        "Berikan penjelasan singkat (3-5 kalimat, bahasa Indonesia) mengapa transaksi ini "
        f"{'terindikasi penipuan' if is_fraud else 'terlihat aman'}.",
        "",
        f"Skor risiko  : {fraud_score:.2%}",
        f"Tingkat risiko: {risk_level}",
    ]

    if explanations:
        lines.append("Indikator    :")
        for indicator in explanations:
            lines.append(f"  - {indicator}")
    else:
        lines.append("Indikator    : tidak ada indikator spesifik yang terdeteksi")

    prompt = "\n".join(lines)
    # Fallback akhir: pastikan string tidak pernah kosong
    return prompt or "Analisis transaksi tidak tersedia."


def get_ai_explanation(fraud_result: dict, api_key: Optional[str] = None) -> str:
    """
    Panggil Claude API dan kembalikan penjelasan teks.

    Raises:
        ValueError: jika API key tidak dikonfigurasi.
        anthropic.APIError: jika panggilan API gagal.
    """
    key = api_key or os.getenv("ANTHROPIC_API_KEY", "")
    if not key:
        raise ValueError(
            "ANTHROPIC_API_KEY belum dikonfigurasi. "
            "Tambahkan ke file .env atau environment variable."
        )

    prompt = _build_prompt(fraud_result)

    client = anthropic.Anthropic(api_key=key)
    response = client.messages.create(
        model="claude-haiku-4-5-20251001",
        max_tokens=512,
        messages=[{"role": "user", "content": prompt}],
    )

    # Ambil teks dari blok pertama; filter jika kosong
    text_blocks = [
        block.text.strip()
        for block in response.content
        if hasattr(block, "text") and block.text and block.text.strip()
    ]
    return text_blocks[0] if text_blocks else "Penjelasan AI tidak tersedia."
