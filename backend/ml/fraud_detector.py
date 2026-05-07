"""
Modul deteksi penipuan bon/struk transaksi digital UMKM.
Ensemble: Isolation Forest + Local Outlier Factor + Aturan Bisnis.
"""

import os
import json
import pickle
import numpy as np
import pandas as pd
from typing import Dict, Tuple, Optional
from sklearn.ensemble import IsolationForest
from sklearn.neighbors import LocalOutlierFactor
from sklearn.preprocessing import RobustScaler
from sklearn.metrics import (
    classification_report, confusion_matrix,
    roc_auc_score, precision_score, recall_score, f1_score,
)

from data_generator import FEATURE_COLUMNS


class FraudDetector:
    MODEL_PATH = os.path.join(os.path.dirname(__file__), "saved_models")

    def __init__(
        self,
        contamination: float = 0.05,
        if_n_estimators: int = 200,
        lof_n_neighbors: int = 20,
        ensemble_weights: Tuple[float, float, float] = (0.40, 0.30, 0.30),
    ):
        self.contamination = contamination
        self.ensemble_weights = ensemble_weights
        self.scaler = RobustScaler()
        self.isolation_forest = IsolationForest(
            n_estimators=if_n_estimators,
            contamination=contamination,
            random_state=42,
            n_jobs=-1,
        )
        self.lof = LocalOutlierFactor(
            n_neighbors=lof_n_neighbors,
            contamination=contamination,
            novelty=True,
            n_jobs=-1,
        )
        self.is_trained = False
        self.feature_stats: Dict = {}
        self.threshold_if: float = 0.0
        self.threshold_lof: float = 0.0

    def fit(self, df: pd.DataFrame) -> "FraudDetector":
        X = df[FEATURE_COLUMNS].copy()
        self._compute_feature_stats(X)
        X_scaled = self.scaler.fit_transform(X)

        if "is_fraud" in df.columns:
            X_train = X_scaled[df["is_fraud"] == 0]
        else:
            X_train = X_scaled

        self.isolation_forest.fit(X_train)
        self.lof.fit(X_train)

        scores_if = self._get_if_scores(X_scaled)
        scores_lof = self._get_lof_scores(X_scaled)

        if "is_fraud" in df.columns:
            labels = df["is_fraud"].values
            self.threshold_if = self._calibrate(scores_if, labels)
            self.threshold_lof = self._calibrate(scores_lof, labels)
        else:
            self.threshold_if = np.percentile(scores_if, 95)
            self.threshold_lof = np.percentile(scores_lof, 95)

        self.is_trained = True
        print(f"Model terlatih | IF: {self.threshold_if:.4f} | LOF: {self.threshold_lof:.4f}")
        return self

    def _calibrate(self, scores: np.ndarray, labels: np.ndarray) -> float:
        best_f1, best_thr = 0, 0.5
        for thr in np.linspace(scores.min(), scores.max(), 100):
            preds = (scores >= thr).astype(int)
            if preds.sum() == 0:
                continue
            f = f1_score(labels, preds, zero_division=0)
            if f > best_f1:
                best_f1, best_thr = f, thr
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

    def predict_single(self, data: Dict) -> Dict:
        if not self.is_trained:
            raise RuntimeError("Model belum dilatih.")

        X = pd.DataFrame([{col: data.get(col, 0) for col in FEATURE_COLUMNS}])
        X_scaled = self.scaler.transform(X)

        score_if = float(self._get_if_scores(X_scaled)[0])
        score_lof = float(self._get_lof_scores(X_scaled)[0])
        score_rule = self._rule_score(data)

        w1, w2, w3 = self.ensemble_weights
        fraud_score = float(np.clip(w1 * score_if + w2 * score_lof + w3 * score_rule, 0, 1))
        is_fraud = fraud_score >= 0.5

        return {
            "is_fraud": is_fraud,
            "fraud_score": round(fraud_score, 4),
            "risk_level": self._risk_level(fraud_score),
            "score_if": round(score_if, 4),
            "score_lof": round(score_lof, 4),
            "score_rule": round(score_rule, 4),
            "explanation": self._explain(data, score_if, score_lof, score_rule),
        }

    def predict_batch(self, df: pd.DataFrame) -> pd.DataFrame:
        if not self.is_trained:
            raise RuntimeError("Model belum dilatih.")
        X_scaled = self.scaler.transform(df[FEATURE_COLUMNS])
        scores_if = self._get_if_scores(X_scaled)
        scores_lof = self._get_lof_scores(X_scaled)
        scores_rule = np.array([self._rule_score(r.to_dict()) for _, r in df[FEATURE_COLUMNS].iterrows()])
        w1, w2, w3 = self.ensemble_weights
        scores = np.clip(w1 * scores_if + w2 * scores_lof + w3 * scores_rule, 0, 1)
        result = df.copy()
        result["fraud_score"] = scores.round(4)
        result["predicted_fraud"] = (scores >= 0.5).astype(int)
        result["risk_level"] = [self._risk_level(s) for s in scores]
        return result

    def _get_if_scores(self, X_scaled):
        raw = self.isolation_forest.decision_function(X_scaled)
        return 1 - (raw - raw.min()) / (raw.max() - raw.min() + 1e-9)

    def _get_lof_scores(self, X_scaled):
        raw = -self.lof.decision_function(X_scaled)
        return (raw - raw.min()) / (raw.max() - raw.min() + 1e-9)

    def _rule_score(self, d: Dict) -> float:
        score = 0.0

        # Diskon terlalu besar
        disc = d.get("discount_pct", 0)
        if disc >= 0.85:
            score += 0.35
        elif disc >= 0.70:
            score += 0.25
        elif disc >= 0.50:
            score += 0.10

        # Transfer ke rekening pribadi
        if d.get("is_transfer_pribadi", 0):
            score += 0.25

        # Platform tidak dikenal
        if not d.get("platform_verified", 1):
            score += 0.20

        # Penjual sangat baru
        age = d.get("seller_age_days", 365)
        if age < 7:
            score += 0.20
        elif age < 30:
            score += 0.10

        # Ada kata-kata mendesak
        if d.get("has_urgent_words", 0):
            score += 0.15

        # Harga jauh di bawah pasar
        ratio = d.get("price_ratio", 1)
        if ratio < 0.20:
            score += 0.20
        elif ratio < 0.40:
            score += 0.10

        # Transaksi tengah malam
        hour = d.get("hour", 12)
        if hour < 5 or hour >= 23:
            score += 0.10

        # Total tidak masuk akal vs subtotal
        sr = d.get("subtotal_ratio", 1)
        if sr < 0.15:
            score += 0.15

        return float(min(score, 1.0))

    def _explain(self, d: Dict, score_if: float, score_lof: float, score_rule: float) -> list:
        reasons = []

        disc = d.get("discount_pct", 0)
        if disc >= 0.70:
            reasons.append(f"Diskon {round(disc*100)}% sangat tidak wajar — potensi penipuan harga")

        if d.get("is_transfer_pribadi", 0):
            reasons.append("Pembayaran ke rekening pribadi, bukan payment gateway resmi")

        if not d.get("platform_verified", 1):
            reasons.append("Transaksi bukan dari platform e-commerce yang terverifikasi")

        age = d.get("seller_age_days", 365)
        if age < 30:
            reasons.append(f"Akun penjual sangat baru ({age} hari) — belum memiliki rekam jejak")

        if d.get("has_urgent_words", 0):
            reasons.append("Terdapat kata-kata mendesak yang biasa digunakan penipu")

        ratio = d.get("price_ratio", 1)
        if ratio < 0.40:
            reasons.append(f"Harga hanya {round(ratio*100)}% dari harga pasar — terlalu murah")

        hour = d.get("hour", 12)
        if hour < 5 or hour >= 23:
            reasons.append(f"Transaksi terjadi pukul {hour}:00 — di luar jam wajar")

        sr = d.get("subtotal_ratio", 1)
        if sr < 0.20:
            reasons.append("Total pembayaran jauh lebih kecil dari subtotal — angka tidak konsisten")

        if score_if > 0.70 and not reasons:
            reasons.append("Pola bon ini berbeda signifikan dari transaksi normal")

        if not reasons:
            reasons.append("Tidak ditemukan indikator penipuan — bon terlihat valid")

        return reasons

    @staticmethod
    def _risk_level(score: float) -> str:
        if score < 0.3:
            return "AMAN"
        elif score < 0.5:
            return "WASPADA"
        elif score < 0.75:
            return "BERISIKO"
        return "BERBAHAYA"

    def evaluate(self, df: pd.DataFrame) -> Dict:
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
            "classification_report": classification_report(y_true, y_pred, target_names=["Normal", "Fraud"], zero_division=0),
            "total_transactions": int(len(df)),
            "total_fraud_detected": int(y_pred.sum()),
            "total_fraud_actual": int(y_true.sum()),
        }

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
            "threshold_if": float(self.threshold_if),
            "threshold_lof": float(self.threshold_lof),
            "feature_stats": self.feature_stats,
            "is_trained": self.is_trained,
        }
        with open(os.path.join(path, "metadata.json"), "w") as f:
            json.dump(meta, f, indent=2)
        print(f"Model disimpan: {path}")
        return path

    @classmethod
    def load(cls, path: str = None) -> "FraudDetector":
        path = path or cls.MODEL_PATH
        with open(os.path.join(path, "metadata.json")) as f:
            meta = json.load(f)
        det = cls(contamination=meta["contamination"], ensemble_weights=tuple(meta["ensemble_weights"]))
        with open(os.path.join(path, "scaler.pkl"), "rb") as f:
            det.scaler = pickle.load(f)
        with open(os.path.join(path, "isolation_forest.pkl"), "rb") as f:
            det.isolation_forest = pickle.load(f)
        with open(os.path.join(path, "lof.pkl"), "rb") as f:
            det.lof = pickle.load(f)
        det.threshold_if = meta["threshold_if"]
        det.threshold_lof = meta["threshold_lof"]
        det.feature_stats = meta["feature_stats"]
        det.is_trained = meta["is_trained"]
        return det
