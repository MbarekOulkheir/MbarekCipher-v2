import matplotlib.pyplot as plt
import seaborn as sns
from collections import Counter

# données
entropies = [
    7.94809, 7.95706, 7.95246, 7.95908, 7.95542, 7.95967, 7.95294,
    7.95407, 7.95293, 7.95424, 7.95881, 7.95368, 7.95842, 7.96099,
    7.95235, 7.95990, 7.95549, 7.95946, 7.95386, 7.95572, 7.95050,
    7.95622, 7.95429
]

# arrondir pour mieux grouper les valeurs proches
entropies_rounded = [round(e, 5) for e in entropies]

# compter les occurrences
counts = Counter(entropies_rounded)
vals = list(counts.keys())
freqs = list(counts.values())

# graphique en barres
plt.figure(figsize=(12, 5))
sns.barplot(x=vals, y=freqs, palette="viridis")
plt.xticks(rotation=45)
plt.title("Nombre de fois que chaque valeur d'entropie apparaît")
plt.xlabel("Entropie (arrondie à 5 décimales)")
plt.ylabel("Nombre de pages")
plt.show()