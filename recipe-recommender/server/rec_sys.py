import numpy as np
import pandas as pd
import unidecode
import ast

from gensim.models import Word2Vec
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from collections import defaultdict

from ingredient_parser import ingredient_parser
from doc_embed import MeanEmbeddingVectorizer
from tfidf_embed import TfidfEmbeddingVectorizer
from word2vec import get_and_sort_corpus

def title_parser(title):
    title = unidecode.unidecode(title)
    return title

def ingredient_parser_final(ingredient):
    """
    neaten the ingredients being outputted
    """
    if isinstance(ingredient, list):
        ingredients = ingredient
    else:
        ingredients = ast.literal_eval(ingredient)

    ingredients = [i for i in ingredients if i.lower() != 'section ingredient']
    ingredients = ",".join(ingredients)
    ingredients = unidecode.unidecode(ingredients)
    return ingredients

def get_recommendations(N, scores):
    """
    Rank scores and output a pandas data frame containing all the details of the top N recipes.
    :param scores: list of cosine similarities
    """
    # Read JSON data into DataFrame
    recipe_df = pd.read_json('recipes.json', orient='index')
    # Remove unnecessary columns
    recipe_df = recipe_df.drop(columns=['author_id', 'date_published', 'picture_path'])
    # Drop rows with missing values
    recipe_df = recipe_df.dropna()
    # Reset index and rename index column
    recipe_df = recipe_df.reset_index().rename(columns={'index': 'recipe_id'})

    # order the scores with and filter to get the highest N scores
    top = sorted(range(len(scores)), key=lambda i: scores[i], reverse=True)[:N]
    # create dataframe to load in recommendations
    recommendation = pd.DataFrame(columns=["recipe", "ingredients", "score", "url"])
    count = 0
    for i in top:
        recommendation.at[count, "recipe"] = title_parser(recipe_df["title"][i])
        recommendation.at[count, "ingredients"] = ingredient_parser_final(
            recipe_df["ingredients"][i]
        )
        recommendation.at[count, "url"] = recipe_df["recipe_link"][i]
        recommendation.at[count, "score"] = f"{scores[i]}"
        count += 1
    return recommendation

def get_recs(ingredients, N=5, mean=False):
    """
    Get the top N recipe recomendations.
    :param ingredients: comma seperated string listing ingredients
    :param N: number of recommendations
    :param mean: False if using tfidf weighted embeddings, True if using simple mean
    """
    # load in word2vec model
    model = Word2Vec.load("model_cbow.bin")
    if model:
        print("Successfully loaded model")
    # load in data
    # Read JSON data into DataFrame
    recipe_df = pd.read_json('recipes.json', orient='index')
    # Remove unnecessary columns
    recipe_df = recipe_df.drop(columns=['author_id', 'date_published', 'picture_path'])
    # Drop rows with missing values
    recipe_df = recipe_df.dropna()
    # Reset index and rename index column
    recipe_df = recipe_df.reset_index().rename(columns={'index': 'recipe_id'})
    # parse ingredients
    recipe_df['parsed'] = recipe_df['ingredients'].apply(lambda x: ingredient_parser(x))
    # create corpus
    corpus = get_and_sort_corpus(recipe_df)
    if mean:
        # get average embdeddings for each document
        mean_vec_tr = MeanEmbeddingVectorizer(model)
        doc_vec = mean_vec_tr.transform(corpus)
        doc_vec = [doc.reshape(1, -1) for doc in doc_vec]
        assert len(doc_vec) == len(corpus)
    else:
        # use TF-IDF as weights for each word embedding
        tfidf_vec_tr = TfidfEmbeddingVectorizer(model)
        tfidf_vec_tr.fit(corpus)
        doc_vec = tfidf_vec_tr.transform(corpus)
        doc_vec = [doc.reshape(1, -1) for doc in doc_vec]
        assert len(doc_vec) == len(corpus)

    # create embeddings for input text
    input = ingredients
    # create tokens with elements
    input = input.split(",")
    # parse ingredient list
    input = ingredient_parser(input)
    # get embeddings for ingredient doc
    if mean:
        input_embedding = mean_vec_tr.transform([input])[0].reshape(1, -1)
    else:
        input_embedding = tfidf_vec_tr.transform([input])[0].reshape(1, -1)

    # get cosine similarity between input embedding and all the document embeddings
    cos_sim = map(lambda x: cosine_similarity(input_embedding, x)[0][0], doc_vec)
    scores = list(cos_sim)
    # Filter top N recommendations
    recommendations = get_recommendations(N, scores)
    return recommendations

if __name__ == "__main__":
    # test
    input = "Butter, Sugar, Vanilla Extract, Sea Salt, Cocoa Powder, All Purpose Flour"
    rec = get_recs(input)
    print(rec)