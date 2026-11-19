import { gql } from '@apollo/client/core'

// GraphQL-документы контент-фабрики (configs/content/api/graphql/content.graphql).
// Мутации возвращают публикацию целиком (PUB_FIELDS), чтобы карточка обновлялась без перезагрузки.

const PUB_FIELDS = `id project_id parent_id title kind rubric sphere status priority planned_date planned_time body hashtags link assignee comment placement_state placements { id platform_id status published_at url comment }`

export const CM_PROJECTS = gql`query CmProjects { contentProjects { id name description topic audience tone keywords competitors manager color } }`
export const CM_PLATFORMS = gql`query CmPlatforms { contentPlatforms { id project_id name kind url audience formats max_length per_week api_status } }`
export const CM_PUBLICATIONS = gql`query CmPublications { contentPublications(limit: 5000) { ${PUB_FIELDS} } }`
export const CM_MATERIALS = gql`query CmMaterials { contentMaterials { id project_id kind entry_date state sphere topic title body tags } }`
export const CM_EVENTS = gql`query CmEvents { contentEvents { id kind title event_date event_time comment } }`
export const CM_MY_ROLE = gql`query CmMyRole { contentMyRole }`
export const CM_REVIEW = gql`query CmReview($project_id: Int, $today: String) { contentReview(project_id: $project_id, today: $today) { code severity publication_id meta } }`
export const CM_IDEAS = gql`query CmIdeas($project_id: Int!, $count: Int, $locale: String) { contentIdeas(project_id: $project_id, count: $count, locale: $locale) { title kind rubric body hashtags planned_date planned_time platform_ids } }`
export const CM_PLAN_PROPOSAL = gql`query CmPlanProposal($project_id: Int!, $days: Int, $locale: String, $today: String) { contentPlanProposal(project_id: $project_id, days: $days, locale: $locale, today: $today) { title kind rubric body hashtags planned_date planned_time platform_ids } }`
export const CM_ADAPTATION = gql`query CmAdaptation($publication_id: ID!, $platform_id: Int!, $locale: String) { contentAdaptation(publication_id: $publication_id, platform_id: $platform_id, locale: $locale) { title kind rubric body hashtags planned_date planned_time platform_ids } }`
export const CM_MEMBERS = gql`query CmMembers { contentMembers { id user_id name email role } }`

export const CM_SAVE_PROJECT = gql`mutation CmSaveProject($id: ID, $input: ContentProjectInput!) { contentSaveProject(id: $id, input: $input) { id } }`
export const CM_DELETE_PROJECT = gql`mutation CmDeleteProject($id: ID!) { contentDeleteProject(id: $id) }`
export const CM_SAVE_PLATFORM = gql`mutation CmSavePlatform($id: ID, $input: ContentPlatformInput!) { contentSavePlatform(id: $id, input: $input) { id } }`
export const CM_DELETE_PLATFORM = gql`mutation CmDeletePlatform($id: ID!) { contentDeletePlatform(id: $id) }`
export const CM_SAVE_PUBLICATION = gql`mutation CmSavePublication($id: ID, $input: ContentPublicationInput!) { contentSavePublication(id: $id, input: $input) { ${PUB_FIELDS} } }`
export const CM_DELETE_PUBLICATION = gql`mutation CmDeletePublication($id: ID!) { contentDeletePublication(id: $id) }`
export const CM_MOVE_PUBLICATION = gql`mutation CmMovePublication($id: ID!, $status: String, $planned_date: String) { contentMovePublication(id: $id, status: $status, planned_date: $planned_date) { id } }`
export const CM_SET_PLACEMENT = gql`mutation CmSetPlacement($publication_id: ID!, $platform_id: Int!, $status: String!, $url: String, $comment: String) { contentSetPlacement(publication_id: $publication_id, platform_id: $platform_id, status: $status, url: $url, comment: $comment) { ${PUB_FIELDS} } }`
export const CM_DUPLICATE_PUBLICATION = gql`mutation CmDuplicatePublication($id: ID!) { contentDuplicatePublication(id: $id) { ${PUB_FIELDS} } }`
export const CM_SAVE_MATERIAL = gql`mutation CmSaveMaterial($id: ID, $input: ContentMaterialInput!) { contentSaveMaterial(id: $id, input: $input) { id } }`
export const CM_DELETE_MATERIAL = gql`mutation CmDeleteMaterial($id: ID!) { contentDeleteMaterial(id: $id) }`
export const CM_SAVE_EVENT = gql`mutation CmSaveEvent($id: ID, $input: ContentEventInput!) { contentSaveEvent(id: $id, input: $input) { id } }`
export const CM_DELETE_EVENT = gql`mutation CmDeleteEvent($id: ID!) { contentDeleteEvent(id: $id) }`
export const CM_SEED_DEMO = gql`mutation CmSeedDemo($locale: String) { contentSeedDemo(locale: $locale) }`
export const CM_SAVE_MEMBER = gql`mutation CmSaveMember($input: ContentMemberInput!) { contentSaveMember(input: $input) { id user_id name email role } }`
export const CM_REMOVE_MEMBER = gql`mutation CmRemoveMember($user_id: ID!) { contentRemoveMember(user_id: $user_id) }`
export const CM_USERS_SEARCH = gql`query CmUsersSearch($search: String) { adminUsers(search: $search, first: 10, page: 1) { data { id name email } } }`

export const CM_AI_STATUS = gql`query CmAiStatus { aiStatus { available source } }`
export const CM_AI_GENERATE = gql`mutation CmAiGenerate($input: ContentAiInput!) { contentAiGenerate(input: $input) { text items } }`
