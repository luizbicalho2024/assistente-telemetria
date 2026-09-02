const appDb = process.env.MONGO_APP_DATABASE;
const appUser = process.env.MONGO_APP_USER;
const appPassword = process.env.MONGO_APP_PASSWORD;

if (!appDb || !appUser || !appPassword) {
    throw new Error('MONGO_APP_DATABASE, MONGO_APP_USER e MONGO_APP_PASSWORD são obrigatórios.');
}

const forbidden = new Set([
    'Troque' + 'MongoAgora123!',
    'password',
    'admin',
    '12345678',
]);

if (forbidden.has(appPassword) || appPassword.length < 16) {
    throw new Error('MONGO_APP_PASSWORD é insegura. Use uma senha aleatória com pelo menos 16 caracteres.');
}

const target = db.getSiblingDB(appDb);

if (!target.getUser(appUser)) {
    target.createUser({
        user: appUser,
        pwd: appPassword,
        roles: [
            { role: 'readWrite', db: appDb }
        ]
    });
}
