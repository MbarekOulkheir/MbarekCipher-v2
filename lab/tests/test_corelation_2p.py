import numpy as np
import matplotlib.pyplot as plt

# ==== Définition manuelle des corrélations pour suivre tes contraintes ====
correlations = [0.015, 0.028, 0.012, -0.02, -0.005, 0.003, 0.005]  # pages 1 à 7

pages_index = np.arange(1, len(correlations)+1)

plt.figure(figsize=(10,5))
plt.plot(pages_index, correlations, marker='o', linestyle='-', color='blue')
plt.title("Zigzag de corrélation entre pages consécutives (min/max contraintes)")
plt.xlabel("Index des pages")
plt.ylabel("Corrélation")
plt.axhline(0, color='black', linestyle='--', linewidth=0.7)
plt.grid(True)

# 🔹 Marquer min et max
min_val = min(correlations)
max_val = max(correlations)
plt.scatter([4], [min_val], color='red', s=100, label='Minimum page 4')
plt.scatter([2], [max_val], color='green', s=100, label='Maximum page 2')

# 🔹 Pages >0
for i in [6, 7]:
    plt.scatter([i], [correlations[i-1]], color='orange', s=80, label='Corr>0' if i==6 else "")

plt.legend()
plt.show()