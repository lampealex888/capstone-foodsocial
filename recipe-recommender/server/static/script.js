const recipeForm = document.getElementById('recipeForm');
const recipeOutput = document.getElementById('recipeOutput');

recipeForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(recipeForm);
    const ingredients = formData.get('ingredients');
    const response = await fetch(`/recipe?ingredients=${ingredients}`);
    const data = await response.json();

    let outputHtml = '<ol>';
    Object.keys(data).forEach(key => {
        const recipe = data[key];
        outputHtml += `<li>
                                    <h3>${recipe.recipe}</h3>
                                    <p style="font-size: smaller;">Ingredients: ${recipe.ingredients}</p>
                                    <p>Score: ${recipe.score}</p>
                                    <p><a href="${recipe.url}" target="_blank">View Recipe!</a></p>
                                </li>`;
    });
    outputHtml += '</ol>';

    recipeOutput.innerHTML = outputHtml;
});
