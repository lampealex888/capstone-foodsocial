import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
import pickle 
import numpy as np

# load in parsed recipe dataset 
df_recipes = pd.read_json("recipes_parsed.json")
for i in range(len(df_recipes)):
    df_recipes['parsed'].iloc[i] = ' '.join(df_recipes['parsed'].iloc[i])
df_recipes['parsed'] = df_recipes.parsed.values.astype('U')
# df_recipes['parsed'] = df_recipes['parsed'].apply(lambda x: ' '.join(x) if isinstance(x, list) else x)

# TF-IDF feature extractor 
tfidf = TfidfVectorizer()
tfidf.fit(df_recipes['parsed'])
tfidf_recipe = tfidf.transform(df_recipes['parsed'])

# save the tfidf model and encodings 
with open("tfidf_encodings.pkl", "wb") as f:
    pickle.dump(tfidf, f)

with open("tfidf.pkl", "wb") as f:
    pickle.dump(tfidf_recipe, f)

    

