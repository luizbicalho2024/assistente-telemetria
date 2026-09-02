const appDb = process.env.MONGO_APP_DATABASE || 'assistente_telemetria';
const appUser = process.env.MONGO_APP_USER || 'assistente';
const appPassword = process.env.MONGO_APP_PASSWORD || 'TroqueMongoAgora123!';

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
