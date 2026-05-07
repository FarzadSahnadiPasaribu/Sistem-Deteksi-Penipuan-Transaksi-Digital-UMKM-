"""
Modul inti deteksi penipuan transaksi UMKM.
Menggunakan ensemble Isolation Forest + Local Outlier Factor.
"""

import os
import json
import pickle
import numpy as np
import pandas as pd
from typing import Dict, Tuple, Optional
from sklearn.ensemble import IsolationForest
from sklearn.neighbors import LocalOutlierFactor
from sklearn.preprocessing import StandardScaler, RobustScaler
from sklearn.metrics import (
    classification_report,
    confusion_matrix,
    roc_auc_score,
    precision_score,
    recall_score,
    f1_score,
)

from data_generator import FEATURE_COLUMNS


class UMKMFraudDetector:
    """
    Detektor penipuan transaksi UMKM menggunakan Anomaly Detection.

    Strategi ensemble:
    1. Isolation Forest — efektif mendeteksi outlier global
    2. Local Outlier Factor (novelty mode) — efektif mendeteksi outlier lokal
    3. Rule-based scoring — logika bisnis UMKM
    Skor akhir = rata-rata tertimbang ketiga pendekatan.
    """

    MODEL_PATH = os.path.join(os.path.dirname(__file__), "saved_models")

    def __init__(
        self,
        contamination: float = 0.05,
        if_n_estimators: int = 200,
        lof_n_neighbors: int = 20,
        ensemble_weights: Tuple[float, float, float] = (0.45, 0.35, 0.20),
    ):
        self.contamination = contamination
        self.ensemble_weights = ensemble_weights

        self.scaler = RobustScaler()
        self.isolation_forest = IsolationForest(
            n_estimators=if_n_estimators,
            contamination=contamination,
            max_features=1.0,
            bootstrap=False,
            random_state=42,
            n_jobs=-1,
        )
        self.lof = LocalOutlierFactor(
            n_neighbors=lof_n_neighbors,
            contamination=contamination,
            novelty=True,
            metric="euclidean",
            n_jobs=-1,
        )

        self.is_trained = False
        self.feature_stats: Dict = {}
        self.threshold_if: float = 0.0
        self.threshold_lof: float = 0.0

    # ------------------------------------------------------------------
    # Training
    # ------------------------------------------------------------------

    def fit(self, df: pd.DataFrame) -> "UMKMFraudDetector":
        """Melatih model dengan data transaksi historis."""
        X = df[FEATURE_COLUMNS].copy()
        self._compute_feature_stats(X)

        X_scaled = self.scaler.fit_transform(X)

        # Hanya gunakan data normal untuk training unsupervised
        if "is_fraud" in df.columns:
            normal_mask = df["is_fraud"] == 0
            X_train = X_scaled[normal_mask]
        else:
            X_train = X_scaled

        self.isolation_forest.fit(X_train)
        self.lof.fit(X_train)

        # Kalibrasi threshold menggunakan seluruh dataset
        scores_if = self._get_if_scores(X_scaled)
        scores_lof = self._get_lof_scores(X_scaled)

        if "is_fraud" in df.columns:
            labels = df["is_fraud"].values
            self.threshold_if = self._calibrate_threshold(scores_if, labels)
            self.threshold_lof = self._calibrate_threshold(scores_lof, labels)
        else:
            self.threshold_if = np.percentile(scores_if, 95)
            self.threshold_lof = np.percentile(scores_lof, 95)

        self.is_trained = True
        print(
            f"Model terlatih | IF threshold: {self.threshold_if:.4f} "
            f"| LOF threshold: {self.threshold_lof:.4f}"
        )
        return self

    def _calibrate_threshold(
        self, scores: np.ndarray, labels: np.ndarray
    ) -> float:
        """Cari threshold yang memaksimalkan F1 pada data training."""
        best_f1, best_thr = 0, 0.5
        for thr in np.linspace(scores.min(), scores.max(), 100):
            preds = (scores >= thr).astype(int)
            if preds.sum() == 0:
                continue
            f1 = f1_score(labels, preds, zero_division=0)
            if f1 > best_f1:
                best_f1, best_thr = f1, thr
        return best_thr

    def _compute_feature_stats(self, X: pd.DataFrame):
        self.feature_stats = {
            col: {
                "mean": float(X[col].mean()),
                "std": float(X[col].std()),
                "max": float(X[col].max()),
            }
            for col in FEATURE_COLUMNS
        }

    # ------------------------------------------------------------------
    # Inference
    # ------------------------------------------------------------------

    def predict_single(self, transaction: Dict) -> Dict:
        """
        Mendeteksi apakah satu transaksi mencurigakan.

        Returns:
            Dict dengan keys:
            - is_fraud (bool)
            - fraud_score (float 0-1, semakin tinggi = semakin mencurigakan)
            - risk_level (str: "RENDAH" | "SEDANG" | "TINGGI" | "KRITIS")
            - explanation (list[str])
        """
        if not self.is_trained:
            raise RuntimeError("Model belum dilatih. Jalankan fit() terlebih dahulu.")

        X = self._prepare_single(transaction)
        X_scaled = self.scaler.transform(X)

        score_if = float(self._get_if_scores(X_scaled)[0])
        score_lof = float(self._get_lof_scores(X_scaled)[0])
        score_rule = self._rule_based_score(transaction)

        w1, w2, w3 = self.ensemble_weights
        fraud_score = w1 * score_if + w2 * score_lof + w3 * score_rule
        fraud_score = float(np.clip(fraud_score, 0, 1))

        is_fraud = fraud_score >= 0.5
        risk_level = self._risk_level(fraud_score)
        explanation = self._explain(transaction, score_if, score_lof, score_rule)

        return {
            "is_fraud": is_fraud,
            "fraud_score": round(fraud_score, 4),
            "risk_level": risk_level,
            "score_isolation_forest": round(score_if, 4),
            "score_lof": round(score_lof, 4),
            "score_rule_based": round(score_rule, 4),
            "explanation": explanation,
        }

    def predict_batch(self, df: pd.DataFrame) -> pd.DataFrame:
        """Prediksi batch untuk seluruh DataFrame."""
        if not self.is_trained:
            raise RuntimeError("Model belum dilatih.")

        X = df[FEATURE_COLUMNS].copy()
        X_scaled = self.scaler.transform(X)

        scores_if = self._get_if_scores(X_scaled)
        scores_lof = self._get_lof_scores(X_scaled)
        scores_rule = np.array([
            self._rule_based_score(row.to_dict())
            for _, row in df[FEATURE_COLUMNS].iterrows()
        ])

        w1, w2, w3 = self.ensemble_weights
        fraud_scores = w1 * scores_if + w2 * scores_lof + w3 * scores_rule
        fraud_scores = np.clip(fraud_scores, 0, 1)

        result = df.copy()
        result["fraud_score"] = fraud_scores.round(4)
        result["predicted_fraud"] = (fraud_scores >= 0.5).astype(int)
        result["risk_level"] = [self._risk_level(s) for s in fraud_scores]
        return result

    # ------------------------------------------------------------------
    # Scoring helpers
    # ------------------------------------------------------------------

    def _get_if_scores(self, X_scaled: np.ndarray) -> np.ndarray:
        """Normalisasi skor Isolation Forest ke [0,1] (1 = paling anomali)."""
        raw = self.isolation_forest.decision_function(X_scaled)
        normalized = 1 - (raw - raw.min()) / (raw.max() - raw.min() + 1e-9)
        return normalized

    def _get_lof_scores(self, X_scaled: np.ndarray) -> np.ndarray:
        """Normalisasi skor LOF ke [0,1]."""
        raw = -self.lof.decision_function(X_scaled)
        normalized = (raw - raw.min()) / (raw.max() - raw.min() + 1e-9)
        return normalized

    def _rule_based_score(self, t: Dict) -> float:
        """Skor berbasis aturan bisnis UMKM (0-1)."""
        score = 0.0

        # Nominal jauh di atas rata-rata historis
        avg = t.get("avg_amount_7d", 1)
        amount = t.get("amount", 0)
        if avg > 0 and amount / avg > 10:
            score += 0.35
        elif avg > 0 and amount / avg > 5:
            score += 0.20
        elif avg > 0 and amount / avg > 3:
            score += 0.10

        # Transaksi di luar jam operasional UMKM (22:00 - 06:00)
        hour = t.get("hour", 12)
        if hour < 6 or hour >= 22:
            score += 0.20

        # Frekuensi transaksi sangat tinggi
        count_1h = t.get("transaction_count_1h", 0)
        if count_1h >= 10:
            score += 0.20
        elif count_1h >= 5:
            score += 0.10

        # Penerima baru + lokasi berubah (kombinasi berisiko)
        if t.get("is_new_recipient", 0) and t.get("location_change", 0):
            score += 0.15

        # Velocity score tinggi
        velocity = t.get("velocity_score", 0)
        if velocity > 0.7:
            score += 0.10

        return float(min(score, 1.0))

    def _prepare_single(self, transaction: Dict) -> pd.DataFrame:
        row = {col: transaction.get(col, 0) for col in FEATURE_COLUMNS}
        return pd.DataFrame([row])

    @staticmethod
    def _risk_level(score: float) -> str:
        if score < 0.3:
            return "RENDAH"
        elif score < 0.5:
            return "SEDANG"
        elif score < 0.75:
            return "TINGGI"
        return "KRITIS"

    def _explain(
        self,
        t: Dict,
        score_if: float,
        score_lof: float,
        score_rule: float,
    ) -> list:
        reasons = []
        avg = t.get("avg_amount_7d", 1) or 1
        amount = t.get("amount", 0)

        if amount / avg > 5:
            rasio = round(amount / avg, 1)
            reasons.append(f"Nominal Rp {amount:,.0f} adalah {rasio}x lebih besar dari rata-rata historis")

        if t.get("hour", 12) < 6 or t.get("hour", 12) >= 22:
            reasons.append(f"Transaksi terjadi di luar jam operasional ({t.get('hour')}:00)")

        if t.get("transaction_count_1h", 0) >= 5:
            reasons.append(f"Frekuensi transaksi tinggi: {t.get('transaction_count_1h')} kali dalam 1 jam")

        if t.get("is_new_recipient", 0):
            reasons.append("Ditransfer ke penerima yang belum pernah bertransaksi sebelumnya")

        if t.get("location_change", 0):
            reasons.append("Terdeteksi perubahan lokasi transaksi yang tidak biasa")

        if t.get("velocity_score", 0) > 0.7:
            reasons.append(f"Kecepatan transaksi mencurigakan (skor: {t.get('velocity_score', 0):.2f})")

        if score_if > 0.65:
            reasons.append("Isolation Forest: pola transaksi sangat berbeda dari historis normal")

        if score_lof > 0.65:
            reasons.append("Local Outlier Factor: transaksi merupakan outlier lokal")

        if not reasons:
            reasons.append("Tidak ada indikator kuat — transaksi terlihat normal")

        return reasons

    # ------------------------------------------------------------------
    # Evaluasi
    # ------------------------------------------------------------------

    def evaluate(self, df: pd.DataFrame) -> Dict:
        """Evaluasi performa model pada data berlabel."""
        if "is_fraud" not in df.columns:
            raise ValueError("DataFrame harus memiliki kolom 'is_fraud'")

        result = self.predict_batch(df)
        y_true = df["is_fraud"].values
        y_pred = result["predicted_fraud"].values
        y_score = result["fraud_score"].values

        return {
            "accuracy": round(float((y_true == y_pred).mean()), 4),
            "precision": round(float(precision_score(y_true, y_pred, zero_division=0)), 4),
            "recall": round(float(recall_score(y_true, y_pred, zero_division=0)), 4),
            "f1_score": round(float(f1_score(y_true, y_pred, zero_division=0)), 4),
            "roc_auc": round(float(roc_auc_score(y_true, y_score)), 4),
            "confusion_matrix": confusion_matrix(y_true, y_pred).tolist(),
            "classification_report": classification_report(
                y_true, y_pred, target_names=["Normal", "Fraud"], zero_division=0
            ),
            "total_transactions": int(len(df)),
            "total_fraud_detected": int(y_pred.sum()),
            "total_fraud_actual": int(y_true.sum()),
        }

    # ------------------------------------------------------------------
    # Simpan / Muat Model
    # ------------------------------------------------------------------

    def save(self, path: str = None) -> str:
        path = path or self.MODEL_PATH
        os.makedirs(path, exist_ok=True)

        with open(os.path.join(path, "scaler.pkl"), "wb") as f:
            pickle.dump(self.scaler, f)
        with open(os.path.join(path, "isolation_forest.pkl"), "wb") as f:
            pickle.dump(self.isolation_forest, f)
        with open(os.path.join(path, "lof.pkl"), "wb") as f:
            pickle.dump(self.lof, f)

        meta = {
            "contamination": self.contamination,
            "ensemble_weights": list(self.ensemble_weights),
            "threshold_if": self.threshold_if,
            "threshold_lof": self.threshold_lof,
            "feature_stats": self.feature_stats,
            "is_trained": self.is_trained,
        }
        with open(os.path.join(path, "metadata.json"), "w") as f:
            json.dump(meta, f, indent=2)

        print(f"Model disimpan di: {path}")
        return path

    @classmethod
    def load(cls, path: str = None) -> "UMKMFraudDetector":
        path = path or cls.MODEL_PATH
        with open(os.path.join(path, "metadata.json")) as f:
            meta = json.load(f)

        detector = cls(
            contamination=meta["contamination"],
            ensemble_weights=tuple(meta["ensemble_weights"]),
        )
        with open(os.path.join(path, "scaler.pkl"), "rb") as f:
            detector.scaler = pickle.load(f)
        with open(os.path.join(path, "isolation_forest.pkl"), "rb") as f:
            detector.isolation_forest = pickle.load(f)
        with open(os.path.join(path, "lof.pkl"), "rb") as f:
            detector.lof = pickle.load(f)

        detector.threshold_if = meta["threshold_if"]
        detector.threshold_lof = meta["threshold_lof"]
        detector.feature_stats = meta["feature_stats"]
        detector.is_trained = meta["is_trained"]
        return detector
